<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 * 	  the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website's Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2011 (or current year) ExamNotes.net inc.
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms;


/**
 * @todo add to autoloaded a function
 * to include cache driver class from Drivers dir
 *
 * @todo add require_once to include CacheDriver and CacheInterface
 * at the beginning of this file
 * use realpath(dirname(__FILE__))
 *
 * But the best solution is probably to just define everything in
 * autoload once before even calling this class
 *
 * @author HP_Administrator
 *
 */
class Cache extends Observer
{

	/**
	 * arrayObject used for storing results
	 * of found values when using __isset() method
	 *
	 * @var object of type ArrayDefaults
	 */
	protected $oTmp;

	/**
	 * arrayObject where key is cache key
	 * and value is integer number of seconds to cache that key
	 * when adding to cache.
	 * @var object of type ArrayDefaults
	 */
	protected $oTtl;

	/**
	 * If set to true the Cache will NOT be cheched
	 * when looking for keys and no values will be put in cache
	 * This can be used for debugging purposes only
	 * Never set this to true on a production server because this would
	 * defeate the purpose of using this class (cache will not be used)
	 * @var bool
	 */
	protected $skipCache = false;


	/**
	 * array of extra params
	 * it is used in getKeyValue function
	 * @var array
	 */
	protected $arrExtra = array();

	/**
	 * Arrays of keys for which values were not found
	 * using get()
	 * Values for these keys will be recreated
	 * and set in cache
	 * @var array
	 */
	protected $aMissingKeys = array();

	/**
	 * Array of value to be returned
	 * to client from get() method
	 * @var array
	 */
	protected $aReturnVals = array();


	protected $oCacheInterface;
	
	/**
	 * Array of identifying tags for cache
	 * 
	 * @var array
	 */
	protected $aTags = null;

	/**
	 * @param Registry $oRegistry
	 */
	public function __construct(Registry $oRegistry)
	{
		d('starting Cache');
		parent::__construct($oRegistry);
		$this->oTtl = new ArrayDefaults(array(), 0);
		$this->oTmp = new ArrayDefaults(array());
		$this->skipCache = $oRegistry->Ini->SKIP_CACHE;
		d('cp');
		if(!$this->skipCache){
			d('cp');
			$this->setCacheEngine(MongoCache::factory($oRegistry));
			$oRegistry->Dispatcher->attach($this);
		}
	}



	/**
	 * Since this is a singleton object
	 * we should disallow cloning
	 * @return void
	 * @throws Cache_Proxy_User_Exception
	 */
	public function __clone()
	{
		throw new DevException('Cloning this object is not allowed.');
	}


	public function __toString()
	{

		return 'object of type CacheHandler';
	}


	/**
	 * Get value for key or array of keys
	 *
	 * @param mixed string|array $key
	 *
	 * @param array $arrExtra array of extra parameters. Some functions
	 * need certain extra parameters
	 *
	 * @return mixed usually a date for a requested key but could be null
	 * in case of some problems
	 *
	 * @throws Cache_Proxy_User_Exception is case the requested key is not a string or array
	 */
	public function get($key, array $arrExtra = array())
	{

		d('$key: '.$key.' $arrExtra: '.print_r(array_keys($arrExtra), 1) );
		$this->arrExtra = $arrExtra;

		if (is_string($key)) {
			d('cp');
			$res = $this->getFromCache($key);
			if (false === $res) {
				$res = $this->getKeyValue($key);
				$this->setValues($key, $res);
			}

			return $res;

		} elseif (is_array($key)) {

			$arrRequestKeys = $key;

			/**
			 * Will be used in case we don't
			 * have memcache extension.
			 */
			$this->aReturnVals = array();
			$this->aMissingKeys = array();

			/**
			 * Requesting several keys at once in an array
			 * will try to get them all at once
			 * but if some could not be found, then
			 * request missing keys one at a time
			 */
			$this->aReturnVals = $this->getFromCache($arrRequestKeys);

			//d(('$this->aReturnVals: '.print_r($this->aReturnVals, 1));

			$this->aReturnVals = (false === $this->aReturnVals) ? array() : $this->aReturnVals;

			$arrRequestKeys = array_flip($arrRequestKeys);

			d('$arrRequestKeys: '.print_r($arrRequestKeys, 1));

			$this->aMissingKeys = array_diff_key($arrRequestKeys, $this->aReturnVals);
			d('$this->aMissingKeys: '.print_r($this->aMissingKeys, 1));

			/**
			 * if we did not get any of the requested keys from memcache,
			 * we need to get it one by one and then add it to $arrValues
			 */
			if (!empty($this->aMissingKeys)) {
				$this->getMissingKeys();
			}

			return $this->aReturnVals;
		}

		throw new DevException('requested key can only be a string or array. Supplied value was of type: '.gettype($key));

	} // end get



	public function setCacheEngine(Interfaces\Cache $oCache = null)
	{
		$this->oCacheInterface = $oCache;

		return $this;
	}

	protected function getMissingKeys()
	{
		d('Could not get all keys from memcache'.print_r($this->aMissingKeys, 1));
		$arrFoundKey = array();

		foreach ($this->aMissingKeys as $key=>$val) {
			$arrFoundKey = $this->getKeyValue($key);
			$arrFoundKeys[$key] = $arrFoundKey;
			$this->setValues($key, $arrFoundKey);
		}

		$this->aReturnVals = array_merge($this->aReturnVals, $arrFoundKeys);
	}



	/**
	 * Finds the method that is responsible
	 * for retreiving data for a requested key
	 * and calls on that method
	 * @param $key
	 * @return mixed a data returned for the requested key or false
	 */
	protected function getKeyValue($key)
	{
		$aRes = explode('_', $key, 2);
		$arg = (array_key_exists(1, $aRes)) ? $aRes[1] : null;

		/**
		 * Check that method exists
		 */
		if (method_exists($this, $aRes[0])) {
			$method = $aRes[0];
			d('Looking for key: '.$key.' Going to use method: '.$method);
			$res = call_user_func(array($this, $method), $arg);
			d('res: '.print_r($res, true));

			return $res;
		}
			
		d('method '.$aRes[0].' does not exist in this object');

		return false;

	} // end getKeyValue


	/**
	 * Generate value of key and set it in cache
	 *
	 * @param $key
	 * @param $ttl optional number of seconds to keep this
	 * key in cache. Default null will result in no expiration for value
	 * @return object $this
	 */
	protected function resetKey($key, $ttl = null)
	{
		$ttl = (is_numeric($ttl)) ? $ttl : $this->oTtl[$key];

		$this->setValues($this->getKeyValue($key), $ttl);

		return $this;
	}


	/**
	 * Tries to get value of $key in cache object
	 * but if value does not exist, does not
	 * attempt to recreate that value
	 *
	 * @param $key
	 * @return mixed value for $key if it exists in cache
	 * or false or null
	 */
	public function tryKey($key)
	{

		return $this->getFromCache($key);

	}


	/**
	 * getter method enables to request a single key from memcache
	 * like this: $hdlCache->keyName;
	 *
	 * @param string $key
	 * @return mixed a value of the requested memcache key
	 * @throws Cache_Proxy_User_Exception if requested key is not a string.
	 */
	public function __get($key)
	{
		if (!is_string($key)) {
			throw new DevException('Cache key must be a string');
		}
		d('looking for '.$key);

		return $this->get($key);

	}


	/**
	 * Magic method to set a single memcache key by
	 * using a string like this:
	 * $this->hdlCache->mykey = 'some val';
	 * this will set the memcache key 'mykey' with
	 * the value 'my val'
	 * Value can be anything - a string, array or object (just not a resource
	 * and NOT a database connection object)
	 *
	 * @param string $strKey
	 * @param mixed $val
	 * @throws Cache_Proxy_User_Exception if value is empty, so basically a string like this:
	 * $this->hdlCache->somekey = ''; is not allowed. setting value to null or
	 * using an empty array as value will also cause this exception.
	 */
	public function __set($strKey, $val)
	{

		if (!is_string($strKey)) {
			throw new DevException('Cache key must be a string');
		}

		if (is_resource($val)) {
			throw new DevException('Value cannot be a resource');
		}

		$this->setValues($strKey, $val);

	} // end __set



	/**
	 * Checks if cache should be used
	 * if yes, then requests value of $key from cache
	 * The calling method already checked that $key is array or string,
	 * so we are sure that if $key is not a string then its an array
	 *
	 * @param $key
	 * @return mixed whatever is returned from $oCache object
	 */
	protected function getFromCache($key)
	{

		if(true === $this->skipCache || null === $this->oCacheInterface){
			d('cp');
			return false;
		}

		if (is_string($key)) {
			d('cp');
			return $this->oCacheInterface->get($key);
		}

		return $this->oCacheInterface->getMulti($key);


	} // end getFromCache


	/**
	 * First checks wheather or not cache should be used
	 * if yes, then adds value to Cache object under the $key
	 * or if $key is array, sets multiple items into cache
	 * if $key is array, it must be an associative array of $key => $val
	 * @param mixed $key string or associative array
	 * @param $val
	 * @return bool
	 */
	public function setValues($key, $val = '')
	{
		if (!$this->skipCache) {
			if (is_string($key)) {
				/**
				 * must have a way to
				 * set an empty result into cache
				 * this is important if
				 * we found that a message does not have any
				 * replies, this makes thread array empty
				 * We must set it into cache otherwise
				 * we will keep doing the same select looking
				 * for the thread array.
				 */
				if (!empty($val) || (0 === $val)) {

					return $this->oCacheInterface->set($key, $val, $this->oTtl[$key], $this->aTags);
				}
			} elseif (!empty($key)) {

				return $this->oCacheInterface->setMulti($key);
			}
		}

		return false;

	} // end setValues



	/**
	 * magic method to check if key exists in Cache
	 * But it does more that just check - it will add the value
	 * of key to $this->oTmp object
	 * so that if we need the value of this key, it will be in the object.
	 * This is memoization
	 *
	 * @param string $key
	 * @return boolean
	 *
	 * @throws Cache_Proxy_User_Exception is $key is not a string
	 */
	public function __isset($key)
	{
		if (!is_string($key)) {
			throw new DevException('$key can only be a string. Supplied argument was of type: '.gettype($key));
		}

		$this->oTmp[$key] = $this->getFromCache($key);
		if ( (null !== $this->oTmp[$key]) && (false !== $this->oTmp[$key])) {

			return true;
		}

		return false;

	}


	/**
	 * Magic method to delete memcache key
	 * using the unset($this->key)
	 * @param $key
	 * @return mixed whatever is returned by cache object
	 * which is usually true on success or false on failure
	 *
	 * @throws Cache_Proxy_User_Exception is $key is not a string
	 */
	public function __unset($key)
	{
		if (!is_string($key)) {

			throw new DevException('$key can only be a string. Supplied argument was of type: '.gettype($key));
		}

		d('Deleting from cache key: '.$key);

		if(!$this->skipCache){
			$ret = $this->oCacheInterface->delete($key);
			d('ret: '.$ret);

			return $ret;
		}
	}

	/**
	 * Handle events
	 * (non-PHPdoc)
	 * @see Lampcms.Observer::main()
	 */
	protected function main(){
		switch($this->eventName){
			case 'onNewQuestions':
			case 'onNewQuestion':
			case 'onResourceDelete':
				$this->__unset('qunanswered');
				$this->__unset('qrecent');
				break;

			case 'onNewAnswer':
				$this->__unset('qunanswered');
				break;
		}
	}


	/**
	 *
	 * Methods for getting specific keys:
	 */


	/**
	 * Generated html string
	 * of links to recent tags fom QA module
	 *
	 * @todo the limit will be in SETTINGS
	 */
	public function qrecent(){
		d('cp');
		$limit = 30;
		$coll = $this->oRegistry->Mongo->getCollection('QUESTION_TAGS');
		$cur = $coll->find(array('i_count' => array('$gt' => 0)), array('tag', 'i_count'))->sort(array('i_ts' => -1))->limit($limit);
		d('got '.$cur->count(true).' tag results');

		$html = \tplLinktag::loop($cur);

		d('html recent tags: '.$html);

		return '<div class="tags-list">'.$html.'</div>';

	}

	
	/**
	 * Generated html string
	 * of links to unanswered tags fom QA module
	 *
	 * @todo the limit will be in SETTINGS
	 */
	public function qunanswered(){
		$limit = 30;
		$coll = $this->oRegistry->Mongo->getCollection('UNANSWERED_TAGS');
		$cur = $coll->find(array(), array('tag', 'i_count'))->sort(array('i_ts' => -1))->limit($limit);
		$count = $cur->count(true);
		d('got '.$count.' tag results');

		$html = \tplUnanstags::loop($cur);

		d('html recent tags: '.$html);

		$ret = '<div class="tags-list">'.$html.'</div>';
		if($count > $limit){
			$ret .= '<div class="moretags"><a href="/tags/unanswered/"><span rel="in">All unanswered tags</span></a>';
		}

		return $ret;
	}


	/**
	 *
	 * @param string $strIp ip address to lookup
	 *
	 * @return object of type GeoipLocation
	 */
	protected function geo($strIp)
	{
		d('getting geodata for ip: '.$strIp);

		$strKey = 'geo_'.$strIp;
		try{
			$oGeoIP = Geoip::getGeoData($strIp);
			$this->oTtl->offsetSet($strKey, 1800);
		} catch (DevException $e){
			e('Unable to get geo record for ip: '.$strIp.' error: ' .$e->getMessage());

			throw $e;
		}

		$this->aTags = array('geo');
		
		return $oGeoIP;
	}

	/**
	 * Creates and returns Acl object
	 *
	 * @return object of type \Lampcms\Acl\Acl
	 */
	protected function Acl()
	{
		d('cp');
		return new \Lampcms\Acl\Acl();

	}


}

