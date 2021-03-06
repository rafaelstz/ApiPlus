<?php
/**
 * Tiago Sampaio
 *
 * Do not edit this file if you want to update this module for future new versions.
 *
 * @category  TS
 * @package   TS_ApiPlus
 *
 * @copyright Copyright (c) 2016 Tiago Sampaio. (http://tiagosampaio.com)
 * @license   https://opensource.org/licenses/MIT The MIT License
 *
 * @author    Tiago Sampaio <tiago@tiagosampaio.com>
 */
class TS_ApiPlus_Model_Http_Request
{

    use TS_ApiPlus_Trait_Data,
        TS_ApiPlus_Trait_Http;


    /** @var Mage_Api_Model_User */
    protected $_user = null;

    /** @var string */
    protected $_data = null;

    /** @var stdClass */
    protected $_decodedData = null;

    /** @var string */
    protected $_resource = null;

    /** @var string */
    protected $_args = null;


    public function __construct()
    {
        $this->initUser();
        $this->initRequestData();
    }


    /**
     * @return bool
     */
    public function validate()
    {
        if ($this->getUser()->getId() && $this->getUser()->getIsActive() != '1') {

            $message = $this->helper()->__('Your account has been deactivated.');
            $this->sendHttpErrorResponse(TS_ApiPlus_Model_Http_Response::HTTP_UNAUTHORIZED, $message);

        } elseif (!Mage::getModel('api/user')->hasAssigned2Role($this->getUser()->getId())) {

            $message = $this->helper()->__('Access denied.');
            $this->sendHttpErrorResponse(TS_ApiPlus_Model_Http_Response::HTTP_UNAUTHORIZED, $message);

        } else {

            if (!$this->getUser()->getId()) {
                $message = $this->helper()->__('Unable to login.');
                $this->sendHttpErrorResponse(TS_ApiPlus_Model_Http_Response::HTTP_UNAUTHORIZED, $message);
            }

        }

        return true;
    }
    

    /**
     * Dispatch the request.
     */
    public function dispatch()
    {
        try {
            $this->validate();

            $result = $this->getResultFromCache();

            if ((false === $result) || empty($result)) {
                $result = $this->getApiServerHandler()->callSimple($this->getResource(), $this->getArgs());
                $result = Zend_Json_Encoder::encode($result);

                $this->saveResultInCache($result);
            }

            $this->getHttpResponse()->setHeader('Content-type', 'application/json', true);
            $this->getHttpResponse()->setBody($result);
        } catch (Exception $e) {
            $this->sendHttpErrorResponse($e->getCode(), $e->getMessage());
        }

        $this->getHttpResponse()->sendResponse();
    }


    /**
     * @return Mage_Api_Model_User
     */
    public function getUser()
    {
        return $this->_user;
    }


    /**
     * @return string
     */
    public function getData()
    {
        if (empty($this->_data)) {
            $this->_data = file_get_contents('php://input');
        }

        return $this->_data;
    }


    /**
     * @return mixed|stdClass
     *
     * @throws Zend_Json_Exception
     */
    public function getDecodedData()
    {
        if (empty($this->_decodedData)) {
            $this->_decodedData = Zend_Json_Decoder::decode($this->getData(), Zend_Json::TYPE_OBJECT);
        }

        return $this->_decodedData;
    }


    /**
     * @return string
     */
    public function getResource()
    {
        if (empty($this->_resource)) {
            $this->_resource = $this->getDecodedData()->resource;
        }

        return $this->_resource;
    }


    /**
     * @return string
     */
    public function getArgs()
    {
        if (empty($this->_args)) {
            $this->_args = $this->getDecodedData()->args;
        }

        return $this->_args;
    }


    /**
     * @return bool|string
     */
    protected function getResultFromCache()
    {
        if (!$this->canUserResultCache()) {
            return false;
        }

        $result = $this->getCoreCacheInstance()->load($this->getCacheKey());

        return $result;
    }


    /**
     * @param string $result
     *
     * @return $this
     */
    protected function saveResultInCache($result)
    {
        if (!$this->canUserResultCache()) {
            return $this;
        }

        $tags     = array(TS_ApiPlus_Model_Config::CACHE_TAG);
        $lifeTime = $this->getResultCacheLifetime();

        $this->getCoreCacheInstance()->save($result, $this->getCacheKey(), $tags, $lifeTime);

        return $this;
    }


    /**
     * @return string
     */
    protected function getCacheKey()
    {
        $prefix   = 'ts_apiplus_';
        $cacheKey = md5($this->getData());
        return $prefix . $cacheKey;
    }


    /**
     * @return $this
     *
     * @throws Zend_Controller_Request_Exception
     */
    protected function initUser()
    {
        $username = (string) $this->getHttpRequest()->getHeader('apiUsername');
        $apiKey   = (string) $this->getHttpRequest()->getHeader('apiKey');

        $this->_user = Mage::getModel('api/user');

        if (false == $this->getUser()->authenticate($username, $apiKey)) {
            $this->sendHttpErrorResponse(TS_ApiPlus_Model_Http_Response::HTTP_UNAUTHORIZED);
        }

        /** @var Mage_Api_Model_Session $session */
        $session = Mage::getSingleton('api/session');
        $session->setData('user', $this->getUser());
        $session->setData('acl',  Mage::getResourceModel('api/acl')->loadAcl());

        return $this;
    }


    /**
     * @return $this
     *
     * @throws Zend_Json_Exception
     */
    protected function initRequestData()
    {
        if (empty($this->getResource())) {
            $this->sendHttpErrorResponse(TS_ApiPlus_Model_Http_Response::HTTP_BAD_REQUEST);
        }

        return $this;
    }

}
