<?php
/**
 * Ensures the filename cannot leave the directory set.
 *
 * @param string $name
 * @return string
 */
function safe_file_name($name)
    {
    // Returns a file name stipped of all non alphanumeric values
    // Spaces are replaced with underscores
    $alphanum = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-';
    $name = str_replace(' ', '_', $name);
    $newname = '';

    for($n = 0; $n < strlen($name); $n++)
        {
        $c = substr($name, $n, 1);
        if(strpos($alphanum, $c) !== false)
            {
            $newname .= $c;
            }
        }

    $newname = substr($newname, 0, 30);

    return $newname;
    }


/**
* Generate a UID for filnames that can be different from user to user (e.g. contact sheets)
* 
* @param integer $user_id
* 
* @return string
*/
function generateUserFilenameUID($user_id)
    {
    if(!is_numeric($user_id) || 0 >= $user_id)
        {
        trigger_error('Bad parameter for generateUserFilenameUID()!');
        }

    global $rs_session, $scramble_key;

    $filename_uid = '';

    if(isset($rs_session))
        {
        $filename_uid .= $rs_session;
        }

    $filename_uid .= $user_id;

    return substr(hash('sha256', $filename_uid . $scramble_key), 0, 15);
    }