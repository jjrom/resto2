<?php

/*
 * RESTo
 * 
 * RESTo - REstful Semantic search Tool for geOspatial 
 * 
 * Copyright 2013 Jérôme Gasperi <https://github.com/jjrom>
 * 
 * jerome[dot]gasperi[at]gmail[dot]com
 * 
 * This software is governed by the CeCILL-B license under French law and
 * abiding by the rules of distribution of free software.  You can  use,
 * modify and/ or redistribute the software under the terms of the CeCILL-B
 * license as circulated by CEA, CNRS and INRIA at the following URL
 * "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and  rights to copy,
 * modify and redistribute granted by the license, users are provided only
 * with a limited warranty  and the software's author,  the holder of the
 * economic rights,  and the successive licensors  have only  limited
 * liability.
 *
 * In this respect, the user's attention is drawn to the risks associated
 * with loading,  using,  modifying and/or developing or reproducing the
 * software by the user in light of its specific status of free software,
 * that may mean  that it is complicated to manipulate,  and  that  also
 * therefore means  that it is reserved for developers  and  experienced
 * professionals having in-depth computer knowledge. Users are therefore
 * encouraged to load and test the software's suitability as regards their
 * requirements in conditions enabling the security of their systems and/or
 * data to be ensured and,  more generally, to use and operate it in the
 * same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL-B license and that you accept its terms.
 * 
 */

class RestoUser{
    
    /*
     * User profile
     */
    public $profile;
    
    /*
     * User cart
     */
    private $cart;
    
    /*
     * Resto rights
     */
    private $rights;
    
    /*
     * Database driver
     */
    private $dbDriver;
    
    /**
     * Constructor
     * 
     * @param string $identifier : can be email (or string) or integer (i.e. uid)
     * @param string $password
     * @param RestoDatabaseDriver $dbDriver
     * @param boolean $setSession
     */
    public function __construct($identifier, $password, $dbDriver, $setSession = true) {
        
        $this->dbDriver = $dbDriver;
        
        if (isset($identifier) && $identifier !== 'unregistered' && $identifier !== -1) {
            
            /*
             * Search for valid profile in session
             */
            if (isset($_SESSION) && isset($_SESSION['profile']) && $_SESSION['profile']['lastsessionid'] === session_id() && $_SESSION['profile']['activated'] && $setSession === true) {
                $this->profile = $_SESSION['profile'];
            }
            else {
                
                $this->profile = $this->dbDriver->getUserProfile($identifier, $password);
                
                /*
                 * Invalid email/password or user not yet activated
                 */
                if ($this->profile['userid'] !== -1) {
                    $this->profile['lastsessionid'] = session_id();
                    $this->dbDriver->updateUserProfile(array(
                        'email' => $identifier,
                        'lastsessionid' => $this->profile['lastsessionid']
                    ));
                }
                if ($setSession) {
                    $_SESSION['profile'] = $this->profile;
                }
            }
        }
        else {
            $this->profile = array(
                'userid' => -1,
                'groupname' => 'unregistered',
                'activated' => false
            );
        }
        
        /*
         * Set rights and cart
         */
        if (isset($this->profile['email'])) {
            $this->rights = new RestoRights($this->profile['email'], $this->profile['groupname'], $this->dbDriver);
            $this->cart = new RestoCart($this, $this->dbDriver, true);
        }
        else {
            $this->rights = new RestoRights('unregistered', 'unregistered', $this->dbDriver);
        }
        
    }
    
    /**
     * Returns rights for collection and/or identifier
     * 
     * @param string $collectionName
     * @param string $featureIdentifier
     */
    public function getRights($collectionName = null, $featureIdentifier = null) {
        return $this->profile['activated'] === false ? $this->rights['groupRights']['unregistered'] : $this->rights->getRights($collectionName, $featureIdentifier);
    }
    
    /**
     * Store user query to database
     * 
     * @param string $method
     * @param string $service
     * @param string $collectionName
     * @param string $featureIdentifier
     * @param array $query
     * @param string $url
     */
    public function storeQuery($method, $service, $collectionName, $featureIdentifier, $query, $url){
        try {
            $this->dbDriver->storeQuery($this->profile['userid'], array(
                'method' => $method,
                'service' => $service,
                'collection' => $collectionName,
                'resourceid' => $featureIdentifier,
                'query' => $query,
                'url' => $url,
                'ip' => $_SERVER['REMOTE_ADDR'],
            ));
        } catch (Exception $e) {}
    }
    
    /**
     * Can User access resource url (i.e. download or visualize) ?
     * 
     * @param string $resourceUrl
     * @param string $token
     * @return boolean
     */
    public function canAccess($resourceUrl, $token) {
        if (!isset($resourceUrl) || !isset($token)) {
            return false;
        }
        return $this->dbDriver->isValidSharedLink($resourceUrl, $token);
    }
    
    /**
     * Can User visualize ?
     * 
     * @param string $collectionName
     * @param string $featureIdentifier
     * @param string $resourceUrl
     * @param string $token
     * @return boolean
     */
    public function canVisualize($collectionName = null, $featureIdentifier = null, $resourceUrl = null, $token = null){
        if ($this->canAccess($resourceUrl, $token)) {
            return true;
        }
        $rights = $this->rights->getRights($collectionName, $featureIdentifier);
        return $rights['visualize'];
    }
    
    /**
     * Can User download ? 
     * 
     * @param string $collectionName
     * @param string $featureIdentifier
     * @param string $resourceUrl
     * @param string $token
     * @return boolean
     */
    public function canDownload($collectionName = null, $featureIdentifier = null, $resourceUrl = null, $token = null){
        if ($this->canAccess($resourceUrl, $token)) {
            return true;
        }
        $rights = $this->rights->getRights($collectionName, $featureIdentifier);
        return $rights['download'];
    }
    
    /**
     * Can User POST ?
     * 
     * @param string $collectionName
     * @return boolean
     */
    public function canPost($collectionName = null){
        $rights = $this->rights->getRights($collectionName);
        return $rights['post'];
    }
    
    /**
     * Can User PUT ?
     * 
     * @param string $collectionName
     * @param string $featureIdentifier
     * @return boolean
     */
    public function canPut($collectionName, $featureIdentifier = null){
        $rights = $this->rights->getRights($collectionName, $featureIdentifier);
        return $rights['put'];
    }
    
    /**
     * Can User DELETE ?
     * 
     * @param string $collectionName
     * @param string $featureIdentifier
     * @return boolean
     */
    public function canDelete($collectionName, $featureIdentifier = null){
        $rights = $this->rights->getRights($collectionName, $featureIdentifier);
        return $rights['delete'];
    }
    
    /**
     * Check if user has to sign license for collection
     * 
     * @param string $collectionName
     */
    public function hasToSignLicense($collectionName) {
        return $this->dbDriver->licenseSigned($this->identifier, $collectionName);
    }
    
    /**
     * Disconnect user i.e. clear session informations
     */
    public function disconnect() {
        if (isset($_SESSION)) {
            unset($_SESSION['profile']);
        }
        return true;
    }
    
    /**
     * Return user cart
     */
    public function getCart() {
        return $this->cart;
    }
    
    /**
     * Add item to cart
     * 
     * @param string $resourceUrl
     */
    public function addToCart($resourceUrl) {
        if ($this->cart->add($resourceUrl, true)) {
            $_SESSION['cart'] = $this->getCart()->getItems();
            return true;
        }
        return false;
    }
    
    /**
     * Remove item from cart
     * 
     * @param string $itemId
     */
    public function removeFromCart($itemId) {
        if ($this->cart) {
            if ($this->cart->remove($itemId, true)) {
                $_SESSION['cart'] = $this->getCart()->getItems();
                return true;
            }
        }
        return false;
    }
}

