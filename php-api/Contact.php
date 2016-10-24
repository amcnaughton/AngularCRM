<?php

/**
 * Created by PhpStorm.
 * User: allan
 * Date: 6/19/15
 * Time: 9:23 AM
 */
class Contact
{
    protected $civiApi;
    public $contact;
    protected $contactId;
    protected $permissions;

    /**
     * Populate Contact object if contact id provided
     *
     * @param null $id
     */
    public function __construct($id = null)
    {
        $this->civiApi = new CiviApi();
        $this->permissions = new CiviAPIPermissions();

        if (!empty($id))
            $this->contact = $this->civiApi->getContact($id);
        else
            $this->contact = $this->civiApi->getContactForCurrentUser();

        if(!empty($this->contact)) {
            $this->contactId = $id;

            $this->contact['phone']['phone'] = $this->formatPhone();
            $this->contact['websites'] = $this->websites();
            $this->contact['sme_tags'] = $this->smeTags();

            if($this->contact['contact_type'] == 'Organization') {
                $this->contact['business_days_open'] = $this->businessDaysOpen();
                $this->contact['hours_of_operation'] = $this->hoursOfOperation();
                $this->contact['employees'] = $this->employees();
                $this->contact['staff_count'] = $this->numberOfStaff();
                $this->contact['board_chair'] = $this->boardChair();
                $this->contact['board_members'] = $this->boardMembers();
                $this->contact['executive_director'] = $this->executiveDirector();
            }

            if($this->civiApi->getContactID() == $id)
                $this->contact['editable'] = true;
            else
                $this->contact = $this->permissions->removePrivateFields($this->contact);
        }
    }

    /**
     * Save contact and its associated details
     */
    public function save()
    {
        if (!empty($this->contact)) {

            if($this->civiApi->getContactID() != $this->contact['contact_id']);
                $this->contact = $this->permissions->removePrivateFields($this->contact);

            $this->civiApi->setContact($this->contact);
        }
    }

    /**
     * Normalize phone number
     *
     * @return mixed
     */
    public function formatPhone()
    {
        if(!empty($this->contact['phone']['phone']))
            return CiviAPIHelper::formatPhoneNumber($this->contact['phone']['phone']);
    }

    /**
     * Lookup a custom field id
     *
     * @param $name
     * @return int|null|string
     */
    public function findCustomFieldId($name)
    {
        if(!empty($this->contact['custom_fields'])) {

            // if passed an index, just return it
            if(is_numeric($name))
                return $name;

            // otherwise find the matching key
            foreach($this->contact['custom_fields'] as $key => $value)
                if($name == $value['name'])
                    return $key;
        }
        return null;
    }

    /**
     * Lookup a custom field value
     *
     * @param $name
     * @return mixed
     */
    public function customFieldValue($name)
    {
        if($index = $this->findCustomFieldId($name)) {

            return $this->contact['custom_values'][$index]['latest'];
        }
    }

    /**
     * Extract list of SME tags
     *
     * @return mixed
     */
    public function smeTags()
    {
        return $this->customFieldValue(CIVI_SUBJECTMATTER_INDEX);
    }

    /**
     * Extract list of websites
     *
     * @return array
     */
    public function websites()
    {
        if(!empty($this->contact['websites'])) {

            foreach($this->contact['websites'] as $site)
                $sites[] = $site['url'];
        }

        return $sites;
    }

    /**
     * Extract hours of operation
     *
     * @return mixed
     */
    public function hoursOfOperation()
    {
        return $this->customFieldValue('Hours_of_Operation');
    }

    /**
     * Helper method for business days open
     *
     * @return array
     */
    protected function businessDaysOpenAsArray()
    {
        $daysOpen = $this->customFieldValue('Business_Days_Open');

        if(!empty($daysOpen))  {

            $days = [
                'org_open_mon' => 'Monday',
                'org_open_tue' => 'Tuesday',
                'org_open_wed' => 'Wednesday',
                'org_open_thu' => 'Thursday',
                'org_open_fri' => 'Friday',
                'org_open_sat' => 'Saturday',
                'org_open_sun' => 'Sunday'
            ];

            return array_keys(array_intersect(array_flip($days),  $daysOpen));
        }
    }

    /**
     * Extract business days open
     *
     * @return string
     */
    public function businessDaysOpen()
    {
        $daysOpen = $this->businessDaysOpenAsArray();

        if(!empty($daysOpen))
            return implode(', ', $daysOpen);
    }

    /**
     * Extract number of staff
     *
     * @return array
     */
    public function numberOfStaff()
    {
        return $this->civiApi->getStaffCount($this->contactId);
    }

    /**
     * Extract list of board members
     *
     * @return array
     */
    public function boardMembers()
    {
        return $this->civiApi->getBoardMembers($this->contactId);
    }

    /**
     * Extract board chair
     *
     * @return array
     */
    public function boardChair()
    {
        return $this->civiApi->getBoardChair($this->contactId);
    }

    /**
     * Extract executive director
     *
     * @return array
     */
    public function executiveDirector()
    {
        return $this->civiApi->getExecutiveDirector($this->contactId);
    }

    /**
     * Extract list of employees
     *
     * @return array
     */
    public function employees()
    {
        return $this->civiApi->getEmployees($this->contactId);
    }
}
