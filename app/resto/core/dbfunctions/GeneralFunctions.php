<?php
/*
 * Copyright 2018 Jérôme Gasperi
 *
 * Licensed under the Apache License, version 2.0 (the "License");
 * You may not use this file except in compliance with the License.
 * You may obtain a copy of the License at:
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

/**
 * RESTo PostgreSQL general functions
 */
class GeneralFunctions
{
    private $dbDriver = null;

    /**
     * Constructor
     *
     * @param RestoDatabaseDriver $dbDriver
     * @throws Exception
     */
    public function __construct($dbDriver)
    {
        $this->dbDriver = $dbDriver;
    }

    /**
     * Check if a table exsist in database
     *
     * @param string $schemaName
     * @param string $tableName
     * @return boolean
     * @throws Exception
     */
    public function tableExists($schemaName, $tableName)
    {
        $results = $this->dbDriver->fetch($this->dbDriver->pQuery('SELECT 1 FROM information_schema.tables WHERE table_schema=$1 AND table_name=$2', array(
            $schemaName,
            $tableName
        )));
        return !empty($results);
    }

    /**
     * Returns shared link initiator email if resource is shared (checked with proof)
     * Returns false otherwise
     *
     * @param string $resourceUrl
     * @param string $token
     * @return boolean
     */
    public function getSharedLinkInitiator($resourceUrl, $token)
    {
        if (!isset($resourceUrl) || !isset($token)) {
            return false;
        }
        $results = $this->dbDriver->fetch($this->dbDriver->pQuery('SELECT userid FROM ' . $this->dbDriver->commonSchema . '.sharedlink WHERE url=$1 AND token=$2 AND validity > now()', array($resourceUrl, $token)));
        return !empty($results) ? $results[0]['userid'] : false;
    }

    /**
     * Create a shared resource and return it
     *
     * @param string $userid
     * @param string $resourceUrl
     * @param integer $duration
     * @return array
     */
    public function createSharedLink($userid, $resourceUrl, $duration = 86400)
    {
        if (!isset($resourceUrl) || !RestoUtil::isUrl($resourceUrl)) {
            return null;
        }
        if (!is_int($duration)) {
            $duration = 86400;
        }
        $results = $this->dbDriver->fetch($this->dbDriver->query('INSERT INTO ' . $this->dbDriver->commonSchema . '.sharedlink (url, token, userid, validity) VALUES (\'' . $this->dbDriver->escape_string( $resourceUrl) . '\',\'' . (RestoUtil::encrypt(mt_rand(0, 100000) . microtime())) . '\',' . $this->dbDriver->escape_string( $userid) . ',now() + ' . $duration . ' * \'1 second\'::interval) RETURNING token', 500, 'Cannot share link'));
        if (count($results) === 1) {
            return array(
                'resourceUrl' => $resourceUrl,
                'token' => $results[0]['token']
            );
        }

        return null;
    }

    /**
     * Save query to database
     *
     * @param string $userid
     * @param array $query
     * @throws Exception
     */
    public function storeQuery($userid, $query)
    {
        return $this->dbDriver->pQuery('INSERT INTO ' . $this->dbDriver->commonSchema . '.log (userid,method,path,query,ip,querytime) VALUES ($1,$2,$3,$4,$5,now())', array(
            $userid ?? null,
            $query['method'] ?? null,
            $query['path'] ?? null,
            $query['query'] ?? null,
            $this->getIp() ?? '127.0.0.1'
        ));
    }

    /**
     * Return true if token is revoked
     *
     * @param string $token
     */
    public function isTokenRevoked($token)
    {
        return !empty($this->dbDriver->fetch($this->dbDriver->pQuery('SELECT 1 FROM ' . $this->dbDriver->commonSchema . '.revokedtoken WHERE token=$1', array($token))));
    }

    /**
     * Revoke token
     *
     * @param string $token
     * @param string $validuntil
     */
    public function revokeToken($token, $validuntil)
    {
        if (isset($token) && !$this->isTokenRevoked($token)) {
            $this->dbDriver->pQuery('INSERT INTO ' . $this->dbDriver->commonSchema . '.revokedtoken (token, validuntil) VALUES($1, $2)', array(
                $token,
                $validuntil ?? null
            ));
        }
        return true;
    }

    /**
     * Return area of input EPSG:4326 WKT
     *
     * @param string $wkt
     * @param string $unit
     */
    public function getArea($wkt, $unit = 'deg')
    {
        // Compute area for surfaces only
        if (strrpos($wkt, 'POLYGON') === false) {
            return 0;
        }

        $result = $this->dbDriver->pQuery('SELECT st_area(' . ($unit === 'deg' ? 'st_geometryFromText($1, 4326)' : 'geography(st_geometryFromText($1, 4326)), false') . ') as area', array($wkt));

        while ($row = pg_fetch_assoc($result)) {
            return (integer) $row['area'];
        }
        return 0;
    }

    /**
     * Convert an array of visibility integers to visibility names
     * 
     * @param array $visibility
     */
    public function visibilityIdsToNames($visibility)
    {
        
        if ( empty($visibility) ) {
            throw new Exception();
        }

        $names = array();
        try {
            $results = $this->dbDriver->fetch($this->dbDriver->query('SELECT id, name FROM ' . $this->dbDriver->commonSchema . '.group WHERE id IN (' .join(',', $visibility). ')'));
            for ($i = 0, $ii = count($results); $i < $ii; $i++) {
                $names[] = $results[$i]['name'];
            }
        } catch (Exception $e) {
            throw new Exception();
        }
        return $names;
        
    }

    /**
     * Convert an array of visibility names to visibility integers
     * 
     * @param array $visibility
     */
    public function visibilityNamesToIds($visibility)
    {
       
        if ( empty($visibility) ) {
            throw new Exception();
        }

        $ids = array();
        $names = array();
        for ($i = count($visibility); $i--;) {
            $names[] = '\'' . $this->dbDriver->escape_string( $visibility[$i] ) . '\'';
        }
        try {
            $results = $this->dbDriver->fetch($this->dbDriver->query('SELECT id, name FROM ' . $this->dbDriver->commonSchema . '.group WHERE name IN (' .join(',', $names). ')'));
            for ($i = 0, $ii = count($results); $i < $ii; $i++) {
                $ids[] = $results[$i]['id'];
            }
        } catch (Exception $e) {
            throw new Exception();
        }

        return $ids;
    }

    /**
     * Return topology analysis
     *
     * @param array $geometry
     * @param array $params
     */
    public function getTopologyAnalysis($geometry, $params)
    {
        $result = null;

        /*
         * Null geometry is allowed in GeoJSON
         */
        if (!isset($geometry)  || !is_array($geometry) || !isset($geometry['type']) || !isset($geometry['coordinates'])) {
            return array(
                'isValid' => true,
                'error' => 'Empty geometry'
            );
        }

        /*
         * Convert to EPSG:4326 if input SRID differs from this projection
         */
        $epsgCode = RestoGeometryUtil::geoJSONGeometryToSRID($geometry);
        if ($epsgCode !== 4326) {
            try {
                $result = pg_fetch_row($this->dbDriver->query_params('SELECT ST_AsGeoJSON(ST_Force2D(ST_Transform(ST_SetSRID(ST_GeomFromGeoJSON($1), ' . $epsgCode . '), 4326))) AS geom', array(
                    json_encode($geometry, JSON_UNESCAPED_SLASHES)
                )), 0, PGSQL_ASSOC);
                $geometry = json_decode($result['geom'], true);
            } catch (Exception $e) {
                $error = '[GEOMETRY] ' . pg_last_error($this->dbDriver->getConnection());
            }
        }
        
        $antimeridian = new AntiMeridian();
        $fixedGeometry = $antimeridian->fixGeoJSON($geometry);
        try {
            $result = pg_fetch_row($this->dbDriver->query_params('WITH tmp AS (SELECT ST_Force2D(ST_SetSRID(ST_GeomFromGeoJSON($1), 4326)) AS geom, ST_Force2D(ST_SetSRID(ST_GeomFromGeoJSON($2), 4326)) AS _geom) SELECT geom, _geom, ST_Force2D(ST_SetSRID(ST_Centroid(_geom), 4326)) AS centroid, Box2D(ST_SetSRID(_geom, 4326)) as bbox FROM tmp', array(
                json_encode($geometry, JSON_UNESCAPED_SLASHES),    
                json_encode($fixedGeometry, JSON_UNESCAPED_SLASHES)
            )), 0, PGSQL_ASSOC);
        } catch (Exception $e) {
            $error = '[GEOMETRY] ' . pg_last_error($this->dbDriver->getConnection());
        }
        
        if (! $result) {
            return array(
                'isValid' => false,
                'error' => $error ?? 'Invalid geometry'
            );
        }
        
        return array(
            'isValid' => true,
            'bbox' => RestoGeometryUtil::box2dTobbox($result['bbox']),
            'geometry' => $result['geom'] === $result['_geom'] ? null : $result['geom'],
            'geom' => $result['_geom'],
            'centroid' => $result['centroid']
        );
    }

    /**
     * Get calling IP
     *
     * @return string
     */
    private function getIp()
    {
        // Try all IPs - the latest, the better
        $best = null;
        foreach (array(
            'REMOTE_ADDR',
            'HTTP_FORWARDED',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_X_FORWARDED_FOR'
        ) as $ip) {
            if (filter_input(INPUT_SERVER, $ip, FILTER_UNSAFE_RAW) !== false && !is_null(filter_input(INPUT_SERVER, $ip, FILTER_UNSAFE_RAW))) {
                $best = filter_input(INPUT_SERVER, $ip, FILTER_UNSAFE_RAW);
            }
        }
        
        return $best;
    }

}
