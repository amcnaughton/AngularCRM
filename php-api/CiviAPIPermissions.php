<?php
/**
 * Support access permissions
 *
 * Created by PhpStorm.
 * User: allan
 * Date: 7/30/15
 * Time: 3:39 PM
 */
class CiviAPIPermissions
{
    protected $permissions;

    public function __construct()
    {
        $this->permissions = parse_ini_file("settings.ini", true);
    }

    /**
     * Remove private fields when viewed by the non-owner
     * 
     * @param $contact
     * @return mixed
     */
    public function removePrivateFields($contact)
    {
        if($contact['contact_type'] == 'Individual')
            $rules = $this->permissions['Individual'];
        else
            if($contact['contact_type'] == 'Organization')
            $rules = $this->permissions['Organization'];

        foreach ($rules as $key => $rule) {
            if($rule == 'private')
                unset($contact[$key]);
        }

        return $contact;
    }
}