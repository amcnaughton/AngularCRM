<?php
/**
 * Created by PhpStorm.
 * User: allan
 * Date: 5/28/15
 * Time: 1:15 PM
 */

define('CIVICRM_SETTINGS_PATH', JPATH_ADMINISTRATOR . '/components/com_civicrm/civicrm.settings.php');
define('CIVICRM_CORE_PATH', JPATH_ADMINISTRATOR . '/components/com_civicrm/civicrm/');
require_once CIVICRM_SETTINGS_PATH;
require_once CIVICRM_CORE_PATH . 'CRM/Core/Config.php';


define('CIVI_RELATIONSHIP_EMPLOYEE', 5);
define('CIVI_RELATIONSHIP_BOARD_MEMBER', 26);
define('CIVI_RELATIONSHIP_BOARD_CHAIR', 25);
define('CIVI_RELATIONSHIP_EXECUTIVE_DIRECTOR', 38);

define('CIVI_TAG_EXECUTIVE_DIRECTOR', 10);

define('CIVI_LOCATION_PRIMARY', 6);

define('CIVI_SUBJECTMATTER_OPTION_GROUP_ID', 116);
define('CIVI_SUBJECTMATTER_INDEX', 56);


class CiviApi
{
    /**
     * We need to call the Civi singleton or CRM_Core_DAO::executeQuery fails
     */
    public function __construct()
    {
        CRM_Core_Config::singleton();

        $this->populateSession();
    }

    /**
     * Remember who we are
     */
    protected function populateSession()
    {
        if($this->getUserID() && $this->getContactID())
            return;

        // save userid
        $user = JFactory::getUser();

        $session = JFactory::getSession();
        $session->set('userid',$user->id);

        // save contact_id
        $result = civicrm_api('UFMatch', 'get', array(
            'uf_id' => $user->id,
            'version' => 3,
        ));

        if (!empty($result)) {
            $session->set('contactid', $result['values'][$result['id']]['contact_id']);
        }
    }

    /**
     * Get the current user id
     *
     * @return mixed
     */
    public function getUserID()
    {
        $session = JFactory::getSession();
        return $session->get('userid');
    }

    /**
     * Get the current contact id
     *
     * @return mixed
     */
    public function getContactID()
    {
        $session = JFactory::getSession();
        return $session->get('contactid');
    }

    /**
     * Get contact for current user
     *
     * @return mixed|null
     */
    public function getContactForCurrentUser()
    {
        $user = JFactory::getUser();
        if (empty($user))
            return null;

        if (empty($this->contactId)) {

            $result = civicrm_api('UFMatch', 'get', array(
                'uf_id' => $user->id,
                'version' => 3,
            ));

            if (!empty($result)) {
                return $this->getContact($result['values'][$result['id']]['contact_id']);
            }
        }

        return null;
    }

    /**
     * Get everything we need to know about a contact
     *
     * @param $contactId
     * @return mixed
     * @throws CiviCRM_API3_Exception
     */
    public function getContact($contactId)
    {
        $result = civicrm_api3('Contact', 'get', array(
            'sequential' => 1,
            'id' => $contactId,
            'api.CustomValue.get' => array('sequential' => 0),
            'api.CustomField.get' => array('sequential' => 0),
            'api.Website.get' => array('sequential' => 0)
        ));

        $result['values'][0]['address'] = $this->getPrimaryAddress($contactId);
        $result['values'][0]['phone'] = $this->getPrimaryPhone($contactId);
        $result['values'][0]['email'] = $this->getPrimaryEmail($contactId);
        $result['values'][0]['relationship_types'] = $this->getRelationshipTypeList();
        $result['values'][0]['states'] = $this->getStates();
        $result['values'][0]['smes'] = $this->getSubjectMatterList();

        $result['values'][0]['websites'] = $result['values'][0]['api.Website.get']['values'];
        unset($result['values'][0]['api.Website.get']);

        $result['values'][0]['custom_fields'] = $result['values'][0]['api.CustomField.get']['values'];
        unset($result['values'][0]['api.CustomField.get']);

        $result['values'][0]['custom_values'] = $result['values'][0]['api.CustomValue.get']['values'];
        unset($result['values'][0]['api.CustomValue.get']);

        return $result['values'][0];
    }

    /**
     * Find desired contacts
     *
     * @param $searchArgs
     * @return array
     */
    public function searchContacts($searchArgs)
    {
        if (!empty($searchArgs['searchvalue']))
            $searchvalue = " AND (((c.contact_type = 'Individual' AND (c.first_name LIKE '%{$searchArgs['searchvalue']}%' OR c.last_name LIKE '%{$searchArgs['searchvalue']}%')) OR
                                   (c.contact_type = 'Organization' AND c.organization_name LIKE '%{$searchArgs['searchvalue']}%')))";

        if (!empty($searchArgs['organization']))
            $organization = " AND (c.employer_id = {$searchArgs['organization']} OR c.id = {$searchArgs['organization']}) ";

        if (!empty($searchArgs['sme_tags'])) {

            foreach ($searchArgs['sme_tags'] as $key => $sme_tag) {

                $sme_like .= " (sme.subjectmatterexpert_56 LIKE '%".chr(1)."{$sme_tag}".chr(1)."%')";
                if ($key < count($searchArgs['sme_tags']) - 1)
                    $sme_like .= " OR ";
            }
            if(!empty($sme_like))
                $sme_like = " AND ( $sme_like) ";
            $sme_join = " LEFT OUTER JOIN civicrm_value_add_l_information_11 AS sme ON c.id = sme.entity_id";
        }

        $having = " ORDER by display_name ";
        if (!empty($searchArgs['zipcode'])) {
            $location = $this->geocodeAddress($searchArgs['zipcode']);

            if (!empty($location)) {

                if (empty($searchArgs['distance']))
                    $radius = 100;
                else
                    $radius = $searchArgs['distance'];

                $distance = ", " . $this->getRadiusSearchSQL_Distance($location['latitude'], $location['longitude']) . ", 1 AS has_distance";
                $having = $this->getRadiusSearchSQL_Filter($radius);
            }
        }

        if ($searchArgs['type'] == 'Individual')
            $filter = "(c.contact_type = 'Individual' AND c.last_name != '')";
        else
            if ($searchArgs['type'] == 'Organization')
                $filter = "(c.contact_type = 'Organization' AND c.organization_name != '')";
            else
                $filter = "((c.contact_type = 'Individual' AND c.last_name != '') OR (c.contact_type = 'Organization' AND c.organization_name != ''))";

        $sql = "SELECT DISTINCT c.id, c.contact_type, c.first_name, c.last_name, c.organization_name, a.street_address, a.city, a.state_province_id, s.abbreviation AS state, p.phone $distance
                FROM civicrm_contact AS c
                LEFT OUTER JOIN civicrm_address AS a ON c.id = a.contact_id AND a.is_primary = 1
                LEFT OUTER JOIN civicrm_phone AS p ON c.id = p.contact_id AND p.is_primary = 1
                LEFT OUTER JOIN civicrm_state_province AS s ON a.state_province_id = s.id
                $sme_join
                WHERE
                  c.is_deleted = 0 AND (c.contact_type = 'Individual' OR c.contact_type = 'Organization') AND
                  $filter
                  $organization
                  $searchvalue
                  $sme_like
                $having
                  ";

        if (empty($distance)) {
            $header[] = ['name' => 'Name'];
            $header[] = ['name' => 'Location'];
            $header[] = ['name' => 'Phone'];
        } else {
            $header[] = ['name' => 'Name'];
            $header[] = ['name' => 'Distance'];
            $header[] = ['name' => 'Location'];
            $header[] = ['name' => 'Phone'];
        }

        $result = CRM_Core_DAO::executeQuery($sql);

        while ($result->fetch()) {
            $record = $result->toArray();
            $rows[] = $this->formatContactRow($record, $distance);
        }

        // rows must exist, even if it's empty
        if(empty($rows))
            $rows = [];

        // filter by relationship
        if(!empty($rows) && !empty($searchArgs['relationship_type'])) {
            $rows = $this->filterByRelationShip($rows, $searchArgs['relationship_type']);
        }
        return ['geosearch' => !empty($distance), 'header' => $header, 'rows' => $rows, 'searchparms' => $searchArgs];
    }

    /**
     * Create the search result row
     *
     * @param $record
     * @param $distance
     * @return array
     */
    protected function formatContactRow($record, $distance)
    {
        if ($record['contact_type'] == 'Organization')
            $name = $record['organization_name'];
        else
            $name = $record['first_name'] . ' ' . $record['last_name'];

        $location = CiviAPIHelper::formatAddress(['city' => $record['city'], 'state' => $record['state']]);
        $phone = CiviAPIHelper::formatPhoneNumber($record['phone']);

        if (empty($distance)) {
            $row = [
                'id' => $record['id'],
                'type' => $record['contact_type'],
                'name' => $name,
                'location' => $location,
                'phone' => $phone];
        } else {
            $row = [
                'id' => $record['id'],
                'type' => $record['contact_type'],
                'name' => $name,
                'distance' => sprintf("%.1f miles", $record['distance']),
                'location' => $location,
                'phone' => $phone];
        }

        return $row;
    }

    /**
     * Remove results that don't have the specified relationship
     *
     * @param $rows
     * @param $relationshipType
     * @return array
     */
    public function filterByRelationShip($rows, $relationshipType)
    {
        // create list of all entities that have this relationship
        $sql = "SELECT id, contact_id_a, contact_id_b
                FROM civicrm_relationship AS r
                WHERE r.is_active = 1 AND r.relationship_type_id = $relationshipType";

        $result = CRM_Core_DAO::executeQuery($sql);

        while ($result->fetch()) {
            $record = $result->toArray();
            $relationships[$record['contact_id_a']] =$record['contact_id_a'];
            $relationships[$record['contact_id_b']] =$record['contact_id_b'];
        }

        // now select rows that have this relationship
        foreach ($rows as $row) {
            if(in_array($row['id'], $relationships))
                $matchedRows[$row['id']] = $row;
        }

        $results = array_values($matchedRows);
        usort($results, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        return $results;
    }

    /**
     * Construct the geo search SQL
     *
     * @param $lat
     * @param $lon
     * @return string
     */
    protected function getRadiusSearchSQL_Distance($lat, $lon)
    {
        $sql = "(((acos(sin((" . $lat . "*pi()/180)) * sin((a.geo_code_1*pi()/180)) + cos((" . $lat . "*pi()/180)) * cos((a.geo_code_1*pi()/180)) * cos(((" . $lon . " - a.geo_code_2)*pi()/180))))*180/pi())*60*1.1515) as distance";
        return $sql;
    }

    /**
     * Construct SQL to order geo search results
     *
     * @param $radius
     * @return string
     */
    protected function getRadiusSearchSQL_Filter($radius)
    {
        $having = "HAVING distance <= " . $radius;
        $sql = $having . " ORDER BY distance ASC";
        return $sql;
    }

    /**
     * Use Google API to geocode a location
     *
     * @param $string
     * @return array|null
     */
    protected function geocodeAddress($string)
    {

        $string = str_replace(" ", "+", urlencode($string));
        $details_url = "http://maps.googleapis.com/maps/api/geocode/json?address=" . $string . "&sensor=false";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $details_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = json_decode(curl_exec($ch), true);

        // If Status Code is ZERO_RESULTS, OVER_QUERY_LIMIT, REQUEST_DENIED or INVALID_REQUEST
        if ($response['status'] != 'OK') {
            return null;
        }

        $geometry = $response['results'][0]['geometry'];

        $array = array(
            'latitude' => $geometry['location']['lat'],
            'longitude' => $geometry['location']['lng'],
            'location_type' => $geometry['location_type'],
        );

        return $array;
    }

    /**
     * Update a contact
     *
     * @param $contact
     * @throws CiviCRM_API3_Exception
     */
    public function setContact($contact)
    {
        $contact['sequential'] = 1;
        $contact['id'] = $contact['contact_id'];

        civicrm_api3('Contact', 'create', $contact);

        if (!empty($contact['address']))
            $this->setPrimaryAddress($contact['address']);

        if (!empty($contact['phone']['phone']))
            $this->setPrimaryPhone($contact['phone']);

        if (!empty($contact['email']))
            $this->setPrimaryEmail($contact['email']);

        $this->setSubjectMatterExpertTags($contact['id'], $contact['sme_tags']);
    }

    /**
     * Get the list of US states from Civi
     *
     * @param int $country_id
     * @return array
     */
    public function getStates($country_id = 1228 /* default to US */)
    {
        $sql = "SELECT * FROM civicrm_state_province WHERE country_id = $country_id";
        $result = CRM_Core_DAO::executeQuery($sql);

        while ($result->fetch()) {
            $data = $result->toArray();
            $states[] = ['state_province_id' => $data['id'], 'abbreviation' => $data['abbreviation']];
        }

        return $states;
    }

    /**
     * Get primary address for a contact
     *
     * @param $contactId
     * @return array
     * @throws CiviCRM_API3_Exception
     */
    public function getPrimaryAddress($contactId)
    {
        $result = civicrm_api3('Address', 'get', array(
            'sequential' => 1,
            'contact_id' => $contactId,
            'is_primary' => 1,
        ));

        if (empty($result['values'][0]))
            return ['contact_id' => $contactId];
        else
            return $result['values'][0];
    }

    /**
     * Update primary address for a contact
     *
     * @param $address
     */
    public function setPrimaryAddress($address)
    {
        if (empty($address['id']))
            unset($address['id']);

        $address['sequential'] = 1;
        $address['location_type_id'] = 2; // work

        try {
            civicrm_api3('Address', 'create', $address);
        } catch (CiviCRM_API3_Exception $e) {
            echo($e->getMessage());
        }
    }

    /**
     * Get primary phone for a contact
     *
     * @param $contactId
     * @return array
     * @throws CiviCRM_API3_Exception
     */
    public function getPrimaryPhone($contactId)
    {
        $result = civicrm_api3('Phone', 'get', array(
            'sequential' => 1,
            'contact_id' => $contactId,
            'is_primary' => 1,
        ));

        if (empty($result['values'][0]))
            return ['contact_id' => $contactId];
        else
            return $result['values'][0];
    }

    /**
     * Update primary phone for a contact
     *
     * @param $phone
     */
    public function setPrimaryPhone($phone)
    {
        if (empty($phone['id']))
            unset($phone['id']);

        $phone['sequential'] = 1;
        $phone['is_primary'] = 1;
        $phone['location_type_id'] = CIVI_LOCATION_PRIMARY;
        $phone['phone_type_id'] = 1;        // phone
        $phone['phone_numeric'] = preg_replace('/\D/', '', $phone['phone']);

//        pr("PHONE");pr($phone);

        try {
            civicrm_api3('Phone', 'create', $phone);
        } catch (CiviCRM_API3_Exception $e) {
            echo($e->getMessage());
        }

    }

    /**
     * Get primary email for a contact
     *
     * @param $contactId
     * @return array
     * @throws CiviCRM_API3_Exception
     */
    public function getPrimaryEmail($contactId)
    {
        $result = civicrm_api3('Email', 'get', array(
            'sequential' => 1,
            'contact_id' => $contactId,
            'is_primary' => 1,
        ));

        if (empty($result['values'][0]))
            return ['contact_id' => $contactId];
        else
            return $result['values'][0];
    }

    /**
     * Update primary email for a contact
     *
     * @param $email
     */
    public function setPrimaryEmail($email)
    {
        if (empty($email['id']))
            unset($email['id']);

        $email['sequential'] = 1;

        try {
            civicrm_api3('Email', 'create', $email);
        } catch (CiviCRM_API3_Exception $e) {
            echo($e->getMessage());
        }
    }

    /**
     * Update SubectMatterExpert tags for a contact
     *
     * @param $id
     * @param $tags
     */
    public function setSubjectMatterExpertTags($id, $tags)
    {
        if (empty($id))
            return;

        try {
            // save SME tags (store as custom_56)
            civicrm_api3('CustomValue', 'create', array(
                'entity_id' => $id,
                'custom_'.CIVI_SUBJECTMATTER_INDEX => $tags
            ));
        } catch (CiviCRM_API3_Exception $e) {
            echo($e->getMessage());
        }
    }

    /**
     * Get entity tags for a contact
     *
     * @param $contactId
     * @return array
     * @throws CiviCRM_API3_Exception
     */
    public function getEntityTags($contactId)
    {
        $result = civicrm_api3('EntityTag', 'get', array(
            'entity_id' => $contactId,
            'options' => array('limit' => 200)
        ));

        if (empty($result['values']))
            return [];
        else {
            foreach ($result['values'] as $key => $value)
                $tags[] = "$key";

            return $tags;
        }
    }

    /**
     * Update entity tags for a contact
     *
     * @param $contactId
     * @param $newTags
     */
    protected function replaceEntityTags($contactId, $newTags)
    {
        $origTags = $this->getEntityTags($contactId);

        // deletions
        if (!empty($origTags)) {

            foreach ($origTags as $tag) {
                if (!in_array($tag, $newTags)) {
                    $deletions[] = $tag;

                    try {
                        civicrm_api3('EntityTag', 'delete', array(
                            'contact_id' => $contactId,
                            'tag_id' => $tag,
                        ));
                    } catch (CiviCRM_API3_Exception $e) {
                        echo($e->getMessage());
                    }
                }
            }
        }

        // additions
        if (!empty($newTags)) {

            foreach ($newTags as $tag) {
                if (!in_array($tag, $origTags)) {
                    $additions[] = $tag;

                    try {
                        civicrm_api3('EntityTag', 'create', array(
                            'contact_id' => $contactId,
                            'tag_id' => $tag,
                        ));
                    } catch (CiviCRM_API3_Exception $e) {
                        echo($e->getMessage());
                    }
                }
            }
        }

    }

    /**
     * Get list of tags
     *
     * @return array
     * @throws CiviCRM_API3_Exception
     */
    public function getContactTagList()
    {
        $result = civicrm_api3('Tag', 'get', array(
            'used_for' => "civicrm_contact",
            'options' => array('limit' => 100),
        ));

        // sort by name since API sort request is ignored
        usort($result['values'], function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        if (empty($result['values']))
            return [];
        else {
            foreach ($result['values'] as $tag)
                $tags[$tag['id']] = ['id' => $tag['id'], 'name' => $tag['name']];

            return $tags;
        }
    }

    /**
     * Get list of relationship types
     *
     * @return array
     * @throws CiviCRM_API3_Exception
     */
    public function getRelationshipTypeList()
    {
        $result = civicrm_api3('RelationshipType', 'get', array(
            'is_active' => 1
        ));

        if (empty($result['values']))
            return [];
        else {
            foreach ($result['values'] as $key => $tag) {
                $label = str_replace(" of", "", $tag['name_a_b']);
                $label = str_replace(" to", "", $label);
                $label = str_replace(" for", "", $label);
                $label = str_replace(" is", "", $label);

                $types[$tag['id']] = ['id' => $tag['id'], 'name' => $label];
            }

            return $types;
        }
    }

    /**
     * Get list of subject matter expert tags
     *
     * @return mixed
     */
    public function getSubjectMatterList()
    {
        $sql = "
              SELECT id, value, label FROM civicrm_option_value
              WHERE option_group_id = " . CIVI_SUBJECTMATTER_OPTION_GROUP_ID . "
              ORDER BY label";

        $result = CRM_Core_DAO::executeQuery($sql);

        while ($result->fetch()) {
            $data = $result->toArray();

            $list[$data['value']] = ['id' => $data['value'], 'name' => $data['label']];
        }

        return $list;
    }

    /**
     * Get list of organizations
     *
     * @return mixed
     */
    public function getOrganizationList()
    {
        $sql = "
              SELECT id, legal_name FROM civicrm_contact
              WHERE is_deleted = 0 AND contact_type = 'Organization' AND legal_name != ''
              ORDER BY TRIM(legal_name)";

        $result = CRM_Core_DAO::executeQuery($sql);

        while ($result->fetch()) {
            $data = $result->toArray();
            $orgs[$data['id']] = ['id' => $data['id'], 'name' => $data['legal_name']];
        }

        return $orgs;
    }

    /**
     * Return number of organization employees
     *
     * @param $contactId
     * @return array
     * @throws CiviCRM_API3_Exception
     */
    public function getStaffCount($contactId)
    {
        $result = civicrm_api3('Relationship', 'getcount', array(
            'sequential' => 1,
            'contact_id_b' => $contactId,
            'relationship_type_id' => CIVI_RELATIONSHIP_EMPLOYEE,
            'is_active' => 1,
        ));

        return $result;
    }

    /**
     * Get list of organization board members
     *
     * @param $contactId
     * @return array
     */
    public function getBoardMembers($contactId)
    {
        return $this->getCiviRelationshipContacts($contactId, CIVI_RELATIONSHIP_BOARD_MEMBER);
    }

    /**
     * Get organization board chair
     *
     * @param $contactId
     * @return mixed
     */
    public function getBoardChair($contactId)
    {
        $result = $this->getCiviRelationshipContacts($contactId, CIVI_RELATIONSHIP_BOARD_CHAIR);
        return $result[0];
    }

    /**
     * Get organization executive director
     *
     * @param $contactId
     * @return mixed
     */
    public function getExecutiveDirector($contactId)
    {
        $result = $this->getCiviRelationshipContacts($contactId, CIVI_RELATIONSHIP_EXECUTIVE_DIRECTOR);
        return $result[0];
    }

    /**
     * Get list of organization employees
     *
     * @param $contactId
     * @return array
     */
    public function getEmployees($contactId)
    {
        return $this->getCiviRelationshipContacts($contactId, CIVI_RELATIONSHIP_EMPLOYEE);
    }

    /**
     * Support function to get relationship contacts
     *
     * @param $contact_id
     * @param $relationship_type
     * @param null $tag
     * @return array
     */
    protected function getCiviRelationshipContacts($contact_id, $relationship_type, $tag = null)
    {
        if (!empty($tag)) {
            $tag_from = " , civicrm_entity_tag AS t ";
            $tag_where = " AND (c.id = t.entity_id AND t.tag_id = $tag) ";
        }
        $sql = "SELECT c.id, c.first_name, c.last_name, c.image_URL FROM civicrm_relationship AS r, civicrm_contact AS c $tag_from
                WHERE
                  r.relationship_type_id = $relationship_type AND r.is_active = 1 AND
                  ((r.contact_id_a = $contact_id AND r.contact_id_b = c.id) OR (r.contact_id_b = $contact_id AND r.contact_id_a = c.id))
                  AND c.is_deleted = 0
                  $tag_where
                ORDER BY
                  c.last_name
                  ";

        $result = CRM_Core_DAO::executeQuery($sql);
        while ($result->fetch()) {
            $data[] = $result->toArray();
        }

        return $data;
    }
}

if (!function_exists('pr')) {
    /**
     * Debug assistant
     *
     * @param $params
     */
    function pr($params)
    {
        if (!empty($_SERVER['JOOMLATOOLS_BOX']))
            echo "<pre>" . print_r($params, true) . "</pre>";
    }
}