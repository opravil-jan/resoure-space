<?php
include dirname(__FILE__) . '/../../include/db.php';
include_once dirname(__FILE__) . '/../../include/general.php';
include dirname(__FILE__) . '/../../include/authenticate.php';
include_once dirname(__FILE__) . '/../../include/node_functions.php';

/*
This allows Asynchronous searches for nodes, either by: node ID or simply by searching for a name (fuzzy search)

Expected functionality:
If we search by node ID, then if found we get its details back
Otherwise, we get all results back based on the name we've searched for.
*/
$node                = getvalescaped('node', 0, true);
$resource_type_field = getvalescaped('resource_type_field', 0, true);
$name                = getvalescaped('name', '');
$rows                = getvalescaped('rows', 10, true);

// Prevent access to fields to which user does not have access to
if(!(checkperm("f{$resource_type_field}") || (checkperm('f*') && !checkperm("f-{$resource_type_field}"))))
    {
    header('HTTP/1.1 401 Unauthorized');
    exit($lang['error-permissiondenied']);
    }

$return               = array();
$found_node_by_ref    = array();
$current_node_pointer = 0;

if(0 == $node && '' == $name)
    {
    $return['error'] = array(
        'status' => 400,
        'title'  => 'Bad Request',
        'detail' => 'Node ID or name incorrect!');

    echo json_encode($return);
    exit();
    }

if(0 < $node && get_node($node, $found_node_by_ref))
    {
    $found_node_by_ref['name'] = i18n_get_translated($found_node_by_ref['name']);

    $return['data'] = $found_node_by_ref;

    echo json_encode($return);
    exit();
    }

// Fuzzy search by node name:
// Translate (i18l) all options and return those that have a match for what client code searched (fuzzy searching still applies)
foreach(get_nodes($resource_type_field, null, true, null, $rows, $name) as $node)
    {
    if($rows == $current_node_pointer)
        {
        break;
        }

    $i18l_name = i18n_get_translated($node['name']);

    // Skip any translated (i18l) names that don't contain what client code searched for
    if(false === mb_strpos(mb_strtolower($i18l_name), $name))
        {
        continue;
        }

    $node['name'] = $i18l_name;

    $return['data'][] = $node;

    // Increment only when valid nodes have been added to the result set
    $current_node_pointer++;
    }

// If by this point we still don't have a response for the request,
// create one now telling client code this is a bad request
if(0 === count($return))
    {
    $return['error'] = array(
        'status' => 400,
        'title'  => 'Bad Request',
        'detail' => 'The request could not be handled by get_nodes.php!');
    }

echo json_encode($return);
exit();