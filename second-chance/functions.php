<?php

function SecondChance_create_table() {
	global $wpdb;
	global $second_chance_db_version;

	$second_chance_db = $wpdb->prefix.'penny_second_chance';

	if($wpdb->get_var("SHOW TABLES LIKE '$second_chance_db'") != $second_chance_db) {
		$sc = "CREATE TABLE `".$wpdb->prefix."penny_second_chance` (
        `id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
        `pid` BIGINT NOT NULL ,
        `uid` BIGINT NOT NULL ,
        `total_bids` BIGINT NOT NULL DEFAULT '0',
        `winner` TINYINT NOT NULL DEFAULT '0',
        `offered` TINYINT NOT NULL DEFAULT '0',
        `accepted` TINYINT NOT NULL DEFAULT '0',
        `status` TINYINT NOT NULL DEFAULT '0'
        ) ENGINE = MYISAM ";
		$wpdb->query($sc);

		require_once(ABSPATH.'wp-admin/includes/upgrade.php');
		dbDelta($sc);

		add_option('second_chance_db_version', $second_chance_db_version);
	}
}
function SecondChance_insert_sc_bid() {
    global $wpdb;
	global $current_user;

	$penny_bids_db = $wpdb->prefix.'penny_bids';
	$second_chance_db = $wpdb->prefix.'penny_second_chance';
	$pid = $_POST['_pid'];
	$uid = $current_user->ID;

	if($uid === 0) {return;}// if user is not logged in.


	//Check if user who has placed a bid was added to my database yet
	$user_exist_in_db = $wpdb->get_row("SELECT uid FROM $second_chance_db WHERE uid = $uid AND pid = $pid");
	if($user_exist_in_db === null) {
		$user_bid_item = $wpdb->get_row("SELECT uid, pid FROM $penny_bids_db WHERE uid = $uid AND pid = $pid");
		if($user_bid_item !== null) {
			$wpdb->insert(
			    $second_chance_db,
			    array(
				    'pid' => $pid,
				    'uid' => $uid,
				    'total_bids' => 2,
			    ));
			return;
		}
	}

	//check if user is currently top bidder
	$top_bidder = (int) SecondChance_get_highest_bidder($pid);//returns UID of top bidder on item
	if($uid !== $top_bidder) {//if UID of bidder does not equal UID of top bidder, increment bid total
		$total_bids = $wpdb->get_var("SELECT total_bids FROM $second_chance_db WHERE uid = $uid AND pid = $pid");
		$total_bids++;
		$wpdb->query("UPDATE $second_chance_db SET total_bids = $total_bids WHERE uid = $uid AND pid = $pid");
	}
}
function SecondChance_update_header() {
	?>
	<script type="text/javascript" src="<?php echo get_bloginfo('template_url'); ?>/js/sc_script.js"></script>
	<?php
}
function SecondChance_get_highest_bidder($pid) {
	global $wpdb;
	$penny_bids_db = $wpdb->prefix.'penny_bids';
	$s = $wpdb->get_var("SELECT uid FROM $penny_bids_db WHERE pid = $pid ORDER BY bid DESC LIMIT 1");
    return $s;
}
function SecondChance_get_assistant_bids($pid, $uid) {
	global $wpdb;
	$penny_assistant_db = $wpdb->prefix.'penny_assistant';
	$u_exist = $wpdb->get_row("SELECT * FROM $penny_assistant_db WHERE uid = $uid AND pid = $pid");
	if($u_exist !== null) {
		$c_s = $wpdb->get_var("SELECT credits_start FROM $penny_assistant_db WHERE uid = $uid AND pid = $pid");
		$c_c = $wpdb->get_var("SELECT credits_current FROM $penny_assistant_db WHERE uid = $uid AND pid = $pid");
		$dif_credits = $c_s - $c_c;
	}
	return $dif_credits;
}
function SecondChance_get_top_bidders() {
	$path = WP_PLUGIN_DIR . "/second-chance/config.txt";
	$fileContents = file_get_contents($path);
	$decoded = json_decode($fileContents, true);

	/*    //To grab open auctions
		$open = array(
			'key' => 'closed',
			'value' => "0",
			'compare' => '=');*/

	$closed = array(
		'key' => 'closed',
		'value' => "1",
		'compare' => '=');

	$args = array(
		'posts_per_page' =>'-1',
		'post_type' => 'auction',
		'post_status' => 'publish',
		'meta_query' => array($closed));

	$the_query = new WP_Query($args);

	if($the_query->have_posts()) {
		while ( $the_query->have_posts() ) : $the_query->the_post();
            if(get_the_ID() > $decoded["last_post_checked"]) {
	            SecondChance_check_offered(get_the_ID()); //Handles `offered` for winner and losers
            }
		endwhile;
		SecondChance_set_last_checked_auction();
	}
}
function SecondChance_check_offered($pid) {
	global $wpdb;
	$penny_bids_db = $wpdb->prefix.'penny_bids';
	$second_chance_db = $wpdb->prefix.'penny_second_chance';

	{
		$winner = $wpdb->get_var("SELECT uid FROM $penny_bids_db WHERE pid = $pid AND winner = 1");
		if($winner >= 1) {
			$winner_offered = $wpdb->get_var("SELECT offered FROM $second_chance_db WHERE pid = $pid AND uid = $winner");
		}
		if($winner_offered === "0") {
			$total_bids = $wpdb->get_var("SELECT total_bids FROM $second_chance_db WHERE pid = $pid AND uid = $winner");
			$total_bids = $total_bids + SecondChance_get_assistant_bids($pid, $winner);
			$wpdb->query("UPDATE $second_chance_db SET total_bids = $total_bids WHERE pid = $pid AND uid = $winner");
			$wpdb->query("UPDATE $second_chance_db SET winner = 1 WHERE pid = $pid AND uid = $winner");
			$wpdb->query("UPDATE $second_chance_db SET offered = 1 WHERE pid = $pid AND uid = $winner");
		}
	}

	//Grab top bidders excluding winner of auction
	//$top_bidders_query = $wpdb->get_results("SELECT offered FROM $second_chance_db WHERE pid = $pid");
	//SELECT * FROM `wp_penny_second_chance` WHERE pid = 23 AND winner <> 1 ORDER BY total_bids DESC LIMIT 5

	$offered_check = $wpdb->get_var(
		"SELECT offered FROM $second_chance_db WHERE pid = $pid AND winner <> 1 ORDER BY total_bids DESC LIMIT 1");

	if($offered_check === "0")
	{
		$top_bidders_query = $wpdb->get_results(
			"SELECT * FROM $second_chance_db WHERE pid = $pid AND winner <> 1 ORDER BY total_bids DESC LIMIT 5");
		//THIS FOR LOOP ONLY GRABS OBJECTS FOR TOP 5 PEOPLE WHO WILL BE OFFERED A SECOND CHANCE
		foreach($top_bidders_query as $bidder_obj) {
			$bidder_offered = $wpdb->get_var("SELECT offered FROM $second_chance_db WHERE uid = $bidder_obj->uid AND pid = $pid");

			if($bidder_offered === "0"):
				$total_bids = $wpdb->get_var("SELECT total_bids FROM $second_chance_db WHERE uid = $bidder_obj->uid AND pid = $pid");
				$total_bids = $total_bids + SecondChance_get_assistant_bids($bidder_obj->pid, $bidder_obj->uid);

				SecondChance_send_email_to_losing_bidders($bidder_obj->uid, $bidder_obj->pid, $total_bids);

				$wpdb->query(
					"UPDATE $second_chance_db
                            SET total_bids = $total_bids
                            WHERE uid = $bidder_obj->uid AND pid = $bidder_obj->pid");

				$wpdb->query(
					"UPDATE $second_chance_db
                            SET offered = 1
                            WHERE uid = $bidder_obj->uid AND pid = $bidder_obj->pid");
			endif;
		}
	}
}
function SecondChance_get_latest_closed_auction() {
	global $wpdb;
	$penny_bids_db = $wpdb->prefix.'penny_bids';
	$newest_pid = $wpdb->get_var("SELECT pid FROM $penny_bids_db WHERE winner = 1 ORDER BY pid DESC LIMIT 1");
	return $newest_pid;
}
function SecondChance_set_last_checked_auction() {
	$path = WP_PLUGIN_DIR . "/second-chance/config.txt";
	$fileContents = file_get_contents($path);
	$decoded = json_decode($fileContents, true);
	$array = array(
		'first_run' => 1,
		'last_post_before_install' => $decoded["last_post_before_install"],
		'last_post_checked' => (int) SecondChance_get_latest_closed_auction()
	);
	$encodedString = json_encode($array);
	file_put_contents($path, $encodedString);
}
function SecondChance_send_email_to_losing_bidders($uid, $pid, $total_bids) {
	$subject 	= get_option('SecondChance_subject');
	$message    = get_option('SecondChance_message');

	$user 			= get_userdata($uid);
	$site_name 		= get_bloginfo('name');

	$post       = get_post($pid);
	$price      = SecondChance_calculate_discounted_price($pid, $total_bids);
	$item_name 	= $post->post_title;
	$item_link 	= get_site_url() . '/my_account/accept-offer/?pid='.$pid.'&user='.$uid;

	$find 		= array('##username##', '##username_email##', '##your_site_name##', '##item_name##' , '##item_link##' , '##item_price##');
	$replace 	= array($user->user_login, $user->user_email, $site_name, $item_name, $item_link, $price);

	$tag		= 'SecondChance_send_email_to_losing_bidders';
	$find 		= apply_filters( $tag . '_find', 	$find );
	$replace 	= apply_filters( $tag . '_replace', $replace );

	$message 	= PennyTheme_replace_stuff_for_me($find, $replace, $message);
	$subject 	= PennyTheme_replace_stuff_for_me($find, $replace, $subject);

	$email = $user->user_email;
//	$admin_email = get_bloginfo('admin_email');
//	PennyTheme_send_email($admin_email, $subject, $message);//admin copy
	PennyTheme_send_email($email, $subject, $message);
}
function SecondChance_calculate_discounted_price($pid, $total_bids) {
	$retail_price = get_post_meta($pid, 'retail_price', 	true);
	$price_var = .3472;
	$price = round($price_var * $total_bids, 2);
	$amt_diff = SecondChance_check_percent_off($retail_price, $price);
	$price = $retail_price - $price;

	if($amt_diff === 0) {
		return $price;
	}
	else {
	    return $amt_diff;
    }
}
function SecondChance_check_percent_off($retail_price, $amount_off) {
    $amt_percent = $amount_off / $retail_price;
    if($amt_percent > .95) {
        return $retail_price * .05;
    }
    else {
        return 0;
    }
}
function SecondChance_money_formatter($price, $cents = 2) {
	$PennyTheme_currency_position = get_option('PennyTheme_currency_position');
	if($PennyTheme_currency_position == "front") {
		return PennyTheme_get_currency()."".PennyTheme_formats($price, $cents);
	}

	return "$".PennyTheme_formats($price,$cents)."".PennyTheme_get_currency();
}
function SecondChance_formats_special($number, $cents = 1) {
	$dec_sep = '.';
	$tho_sep = ',';
	if (is_numeric($number)) {
		if (!$number) {
			$money = ($cents == 2 ? '0'.$dec_sep.'00' : '0');
		} else { // value
			if (floor($number) == $number) {
				$money = number_format($number, ($cents == 2 ? 2 : 0), $dec_sep, '' );
			} else {
				$money = number_format(round($number, 2), ($cents == 0 ? 0 : 2), $dec_sep, '' );
			}
		}
		return $money;
	}
}
function SecondChance_update_row($status, $uid, $pid) {
    global $wpdb;
	$second_chance_db = $wpdb->prefix.'penny_second_chance';
    $wpdb->query("UPDATE $second_chance_db SET status = $status WHERE uid = $uid AND $pid = pid");
}