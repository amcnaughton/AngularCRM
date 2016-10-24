<?php
/**
 * Exchange REST API handler
 *
 * Author: Allan McNaughton
 */
define('_JEXEC', 1);
define('_API', 1);
define('JPATH_BASE', dirname(dirname(dirname(__FILE__))));

error_reporting(error_reporting() & ~E_NOTICE);

// Include the Civi framework
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';
require_once 'civiapi.php';
require_once 'Contact.php';
require_once 'CiviAPIHelper.php';
require_once 'CiviAPIPermissions.php';

$application = JFactory::getApplication('site');
$application->initialise();

require '../Slim/Slim.php';

// Instantiate Slim
\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim(array(
    'mode' => 'development'
));

$app->_db = JFactory::getDbo();
$app->_input = JFactory::getApplication()->input;

$app->view(new \JsonApiView());
$app->add(new \JsonApiMiddleware());


/**
 * Get all the details for a contact
 */
$app->map('/contacts/:id', function ($id) use ($app) {

    $result = new Contact($id);

    $app->render(200, array(
            $result->contact
        )
    );
}
)->via('GET');

/**
 * Save/update a contact
 */
$app->map('/contacts/:id', function ($id) use ($app) {

    $contact = new Contact($id);
    $updatedContact = json_decode($app->request->getBody(), true);

    // permissions check
    if($contact->contact['editable'] != true || $contact->contact['contact_id'] != $updatedContact['contact_id']) {
        $app->render(403);
    }
    else {

        $contact->contact = $updatedContact;
        $contact->save();

        $app->render(200);
    }
}
)->via('POST');

/**
 * Find contacts
 */
$app->map('/contacts', function () use ($app) {

    $params['searchvalue'] = $_GET['name'];
    $params['organization'] = $app->_input->getInt('organization');
    $params['entity_tags'] = $_GET['entity_tags'];
    $params['sme_tags'] = $_GET['sme_tags'];
    $params['relationship_type'] = $_GET['relationship_type'];
    $params['zipcode'] = $app->_input->get('zipcode');
    $params['distance'] = $app->_input->getInt('distance');
    $params['type'] = $app->_input->get('type');

    $result = (new CiviApi())->searchContacts($params);

    $app->render(200, array(
            $result
        )
    );
}
)->via('GET');

/**
 * Get a list of states
 */
$app->map('/states', function () use ($app) {

    $result = (new CiviApi())->getStates();

    $app->render(200, array(
            $result
        )
    );
}
)->via('GET');

/**
 * Get a list of subject matter experts
 */
$app->map('/smes', function () use ($app) {

    $result = (new CiviApi())->getSubjectMatterList();

    $app->render(200, array(
            $result
        )
    );
}
)->via('GET');

/**
 * Get a list of organizations
 */
$app->map('/organizations', function () use ($app) {

    $result = (new CiviApi())->getOrganizationList();

    $app->render(200, array(
            $result
        )
    );
}
)->via('GET');

/**
 * Get a list of tags
 */
$app->map('/tags', function () use ($app) {

    $result = (new CiviApi())->getContactTagList();

    $app->render(200, array(
            $result
        )
    );
}
)->via('GET');

/**
 * Get a list of relationship types
 */
$app->map('/relationship_types', function () use ($app) {

    $result = (new CiviApi())->getRelationshipTypeList();

    $app->render(200, array(
            $result
        )
    );
}
)->via('GET');

$app->run();

// Call this at each point of interest, passing a descriptive string
function prof_flag($str)
{
    global $prof_timing, $prof_names;
    $prof_timing[] = microtime(true);
    $prof_names[] = $str;
}

// Call this when you're done and want to see the results
function prof_print()
{
    global $prof_timing, $prof_names;
    $size = count($prof_timing);
    for($i=0;$i<$size - 1; $i++)
    {
        echo "<b>{$prof_names[$i]}</b><br>";
        echo sprintf("&nbsp;&nbsp;&nbsp;%f<br>", $prof_timing[$i+1]-$prof_timing[$i]);
    }
    echo "<b>{$prof_names[$size-1]}</b><br>";
}
