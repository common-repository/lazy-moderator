<?php
/*
Plugin Name: Lazy Moderator
Plugin URI: http://www.phoenixheart.net/wp-plugins/lazy-moderator/
Description: Comment moderation for the lazy! Provides quick-yet-secure one-click links to moderate comments.
Version: 1.1.1
Author: Phan An
Author URI: http://www.phoenixheart.net
*/

global $wpdb, $lmoderator_plugin_name, $lmoderator_plugin_dir, $lmoderator_settings, $lmoderator_slug, $lmoderator_token_table;

$lmoderator_plugin_name = 'Lazy Moderator';
$lmoderator_slug = 'lazy-moderator';
$lmoderator_plugin_dir = get_settings('siteurl') . "/wp-content/plugins/$lmoderator_slug/";
$lmoderator_token_table = "{$wpdb->prefix}lazy_moderator_tokens";

$lmoderator_settings = array(
    'secret' => lmoderator_generate_random_string(),
);

load_plugin_textdomain($lmoderator_slug);

/**
* Function to be triggered during plugin activation.
* Initializes the settings and creates the token table.
* 
*/
function lmoderator_install()
{
    global $lmoderator_settings, $lmoderator_slug, $lmoderator_token_table, $wpdb;
    
    update_option($lmoderator_slug, $lmoderator_settings);
    
    // create the token table
    if (!$wpdb->query("SHOW TABLES FROM `{$wpdb->db_name}` LIKE '$lmoderator_token_table'"))
    {
        $wpdb->query("CREATE TABLE `$lmoderator_token_table` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `comment_id` int(11) NOT NULL,
          `approve_token` char(40) COLLATE utf8_unicode_ci NOT NULL,
          `trash_token` char(40) COLLATE utf8_unicode_ci NOT NULL,
          `spam_token` char(40) COLLATE utf8_unicode_ci NOT NULL,
          `delete_token` char(40) COLLATE utf8_unicode_ci NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
    }
}

register_activation_hook(__FILE__, 'lmoderator_install');

/**
* Function to be triggered during plugin de-activation.
* Clear the settings and drop the token table.
* 
*/
function lmoderator_uninstall()
{
    global $lmoderator_settings, $lmoderator_slug, $lmoderator_token_table, $wpdb;
    
    delete_option($lmoderator_slug);
    
    // delete the token table
    $wpdb->query("DROP TABLE IF EXISTS `$lmoderator_token_table`");
}

register_deactivation_hook(__FILE__, 'lmoderator_uninstall');

/**
* Filter that gets called when a notify email is about to be sent out to a moderator.
* 
* @param string The email (notification) message
* @param int    ID of the comment
*/
function lmoderator_notify_moderator($notify_message, $comment_id)
{
    global $lmoderator_slug;
    
    // if the insert fails for any reason, just return the original notif message
    if (!$data = lmoderator_generate_tokens($comment_id))
    {
        return $notify_message;
    }
    
    // otherwise, inject our one-click links
    $notify_message .= "\r\n";
    $notify_message .= sprintf(__('One-click approve: %s ', $lmoderator_slug), site_url("?lmoderator_a=a&c={$data['comment_id']}&t={$data['approve_token']}")) . "\r\n";
    if (EMPTY_TRASH_DAYS)
    {
        $notify_message .= sprintf(__('One-click trash: %s ', $lmoderator_slug), site_url("?lmoderator_a=t&c={$data['comment_id']}&t={$data['trash_token']}")) . "\r\n";
    }
    else
    {
        $notify_message .= sprintf(__('One-click delete: %s ', $lmoderator_slug), site_url("?lmoderator_a=d&c={$data['comment_id']}&t={$data['spam_token']}")) . "\r\n";
    }
    $notify_message .= sprintf(__('One-click spam: %s '), site_url("?lmoderator_a=s&c={$data['comment_id']}&t={$data['spam_token']}")) . "\r\n";

    lmoderator_log($notify_message);
    return $notify_message;
}

add_filter('comment_moderation_text', 'lmoderator_notify_moderator', 10, 2);

/**
* Filter that gets called when a notify email is about to be sent out to a post author.
* 
* @param string The email (notification) message
* @param int    ID of the comment
*/
function lmoderator_notify_postauthor($notify_message, $comment_id)
{
    global $lmoderator_slug;
    
    // if the insert fails for any reason, just return the original notif message
    if (!$data = lmoderator_generate_tokens($comment_id))
    {
        return $notify_message;
    }
    
    // otherwise, inject our one-click links
    $notify_message .= "\r\n";
    if (EMPTY_TRASH_DAYS)
    {
        $notify_message .= sprintf(__('One-click trash: %s ', $lmoderator_slug), site_url("?lmoderator_a=t&c={$data['comment_id']}&t={$data['trash_token']}")) . "\r\n";
    }
    else
    {
        $notify_message .= sprintf(__('One-click delete: %s ', $lmoderator_slug), site_url("?lmoderator_a=d&c={$data['comment_id']}&t={$data['spam_token']}")) . "\r\n";
    }
    $notify_message .= sprintf(__('One-click spam: %s '), site_url("?lmoderator_a=s&c={$data['comment_id']}&t={$data['spam_token']}")) . "\r\n";

    // lmoderator_log($notify_message);
    return $notify_message;
}

add_filter('comment_notification_text', 'lmoderator_notify_postauthor', 10, 2);

/**
* Generates an array of tokens for different moderation actions
* 
* @param int    ID of the comment
*/
function lmoderator_generate_tokens($comment_id)
{
    global $lmoderator_slug, $lmoderator_token_table, $wpdb;
    
    // Do we have tokens for this comment ID already?
    // If yes, just retrieve them back
    $tokens = $wpdb->get_row($wpdb->prepare("SELECT comment_id, approve_token, trash_token, spam_token, delete_token 
        FROM $lmoderator_token_table
        WHERE comment_id=%d", $comment_id), ARRAY_A);
    
    if ($tokens) return $tokens;
    
    
    // No, we don't have existing tokens for this comment
    // Just generate them.
    
    $settings = get_option($lmoderator_slug);
    
    // generate totally random tokens for approve, trash, and spam actions
    // using a combination of sha1(), our random string generator, md5() and uniqid()
    // is this an overkill?
    $data = array(
        'comment_id'    => $comment_id,
        'approve_token' => sha1($settings['secret'] . lmoderator_generate_random_string(128) . md5($comment_id . uniqid())),
        'trash_token'   => sha1($settings['secret'] . lmoderator_generate_random_string(128) . md5($comment_id . uniqid())),
        'spam_token'    => sha1($settings['secret'] . lmoderator_generate_random_string(128) . md5($comment_id . uniqid())),
        'delete_token'  => sha1($settings['secret'] . lmoderator_generate_random_string(128) . md5($comment_id . uniqid())),
    );
    
    // try inserting the tokens into database
    // if the insert fails, return FALSE, else return the token array
    if ($wpdb->insert($lmoderator_token_table, $data))
    {
        return $data;
    }
    return FALSE;
}

/**
* Hooks into init() action to moderate the comment if applicable
* 
*/
function lmoderator_init_hook()
{
    $lmoderator_a = $c = $t = FALSE;
    extract($_GET);
    
    switch ($lmoderator_a)
    {
        case 'a':
            lmoderator_moderate('approve', $c, $t);
            break;
        case 't':
            lmoderator_moderate('trash', $c, $t);
            break;
        case 's':
            lmoderator_moderate('spam', $c, $t);
            break;
        case 'd':
            lmoderator_moderate('delete', $c, $t);
            break;
        default:
            return;
    }
    die();
}

add_action('init', 'lmoderator_init_hook', PHP_INT_MAX);

/**
* Quickly moderates the comment 
* 
* @param string Status of the comment to be set (approve/trash/spam)
* @param mixed $comment_id
* @param mixed $token
*/
function lmoderator_moderate($status, $comment_id, $token)
{
    global $lmoderator_token_table, $lmoderator_slug, $wpdb;
    
    // *** Validate the token
    // First get the stored token from database (if any)
    $stored_token = $wpdb->get_var($wpdb->prepare("SELECT {$status}_token FROM $lmoderator_token_table WHERE comment_id=%d", $comment_id));
    
    // then compare it with the sent token. If mismatch, send error.
    if (strcmp($token, $stored_token))
    { 
        lmoderator_respond('validation.jpg', __('Validation failed', $lmoderator_slug), __('Validation failed. The request, therefore, is denied.', $lmoderator_slug), 'HTTP/1.0 400 Bad Request');
        return;
    }
    
    // does the comment exist?
    if (!$comment = get_comment($comment_id))
    {
        lmoderator_respond('404.gif', __('Comment not found', $lmoderator_slug), __('The comment cannot be found. Maybe someone has deleted it.', $lmoderator_slug), 'HTTP/1.0 404 Not Found');
        return;
    }
        
    switch ($status)
    {
        case 'delete':
            wp_delete_comment($comment_id, TRUE);
            $img = 'delete.jpg';
            $title = __('Comment deleted', $lmoderator_slug);
            $info = sprintf(__('The comment from %s has been permanently deleted.', $lmoderator_slug), "<strong>{$comment->comment_author}</strong>");
            break;
        case 'approve':
            if ($comment->comment_approved == 1)
            {
                $img = 'already-approved.jpg';
                $title = __('Comment already approved', $lmoderator_slug);
                $info = sprintf(__('The comment from %s is already approved. Re-approval makes little sense.', $lmoderator_slug), "<strong>{$comment->comment_author}</strong>");
            }
            else
            {
                wp_set_comment_status($comment_id, $status);
                $img = 'approve.jpg';
                $title = __('Comment approved', $lmoderator_slug);
                $info = sprintf(__('The comment from %s has been approved.', $lmoderator_slug), "<strong>{$comment->comment_author}</strong>");
            }
            break;
        case 'spam':
            wp_set_comment_status($comment_id, $status);
            $img = 'spam.jpg';
            $title = __('Comment marked as spam', $lmoderator_slug);
            $info = sprintf(__('The comment from %s has been marked as spam.', $lmoderator_slug), "<strong>{$comment->comment_author}</strong>");
            break;
        case 'trash':
            wp_trash_comment($comment_id);
            $img = 'trash.jpg';
            $title = __('Comment moved to trash', $lmoderator_slug);
            $info = sprintf(__('The comment from %s has been moved to trash.', $lmoderator_slug), "<strong>{$comment->comment_author}</strong>");
            break;
        default:
            return;
    }
    
    lmoderator_respond($img, $title, $info);
}

/**
* Sends a HTTP response in HTML format
* 
* @param string The URL of the image
* @param string Title of the page (basically, the status of the moderation)
* @param string Additional HTTP header
*/
function lmoderator_respond($img, $title, $info, $header = FALSE)
{
    global $lmoderator_plugin_dir, $lmoderator_plugin_name;
    
    if ($header)
    {
        header($header);
    }
    
    echo <<<EOT
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="robots" content="noindex,nofollow" />
<title>$title</title>
<style>
body, html
{
    margin: 0;
    padding: 0;
    background: #ddd;
    font-family: Heveltica, Arial, Tahoma, sans-serif;
    font-size: 12px;
    text-align: center;
    color: #666;
    text-shadow: 0 1px #eee;
}
img
{
    display: block;
    padding: 10px;
    background: #fff;
    box-shadow: 0 0 5px 5px #ccc;
    margin: 40px auto 20px auto;
}
#info
{
    padding: 0 0 5px;
}
#credits
{
    margin: 0; 
    padding: 0;
    font-size: 11px;
}
#credits a
{
    font-weight: bold;
    color: #ae2500;
    text-decoration: none;
    -webkit-transition: color .2s linear;
    -moz-transition: color .2s linear;
}
#credits a:hover
{
    color: #f83500;
}
</style>
</head>
<body>
<img src="{$lmoderator_plugin_dir}img/$img" />
<div id="info">$info</div>
<p id="credits">
    Happy being a <a href="http://www.phoenixheart.net/wp-plugins/lazy-moderator/">$lmoderator_plugin_name</a>!
    </p>
</div>
</body>
</html>
EOT;
}

/**
* Generates a random string
* Credit: http://www.php.net/manual/en/function.mt-rand.php#105587
* @param integer Length of the string
*/
function lmoderator_generate_random_string($len = 40)
{
    if (@is_readable('/dev/urandom')) 
    { 
        $f = fopen('/dev/urandom', 'r'); 
        $urandom = fread($f, $len); 
        fclose($f); 
    } 

    $return = ''; 
    for ($i = 0; $i < $len; ++$i) 
    { 
        if (!isset($urandom)) 
        { 
            if ($i%2==0) mt_srand(time()%2147 * 1000000 + (double)microtime() * 1000000); 
            $rand=48 + mt_rand()%64; 
        } 
        else 
        {
            $rand=48 + ord($urandom[$i])%64; 
        }

        if ($rand > 57) 
        {
            $rand += 7; 
        }
        if ($rand > 90) 
        {
            $rand += 6; 
        }

        if ($rand == 123)
        { 
            $rand = 45; 
        }
        if ($rand == 124) 
        {
            $rand = 46; 
        }
        
        $return .= chr($rand); 
    } 
    return $return; 
}

/**
* A dead-simple function to log messages and errors
* 
* @param mixed $txt
*/
function lmoderator_log($txt)
{
    $handle = fopen(dirname(__FILE__) . '/log', 'a');
    fwrite($handle, $txt . PHP_EOL);
    fclose($handle);
}