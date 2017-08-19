<?php

/*
Plugin Name: Second Chance
Plugin URI: http://www.hanneswidrig.com
Description: This Plugin provides magical traits
Version: 1.1.2
Author: Hannes Widrig
Author URI: http://www.hanneswidrig.com
License: A "Slug" license name e.g. GPL2
*/

add_action('admin_menu', 'register_second_chance_menu');
add_action('admin_enqueue_scripts', 'register_scripts');
add_action('wp_ajax_bid_now_live_me', 				'SecondChance_insert_sc_bid');
add_action('wp_ajax_nopriv_bid_now_live_me', 	    'SecondChance_insert_sc_bid');
add_action('template_redirect', 'SecondChance_get_top_bidders');
add_action('template_redirect', 'SecondChance_payment');
update_option('SecondChance_subject', 'A second chance to purchase the item you bid on!');
update_option('SecondChance_message', 'Hello ##username##,'.PHP_EOL.PHP_EOL.
                                      'You did not win the auction, but you we can offer you the opportunity to purchase this item at a discounted price.'.PHP_EOL.
                                      'The ##item_name## will cost $##item_price##. ##item_link##'.PHP_EOL.PHP_EOL.
                                      'Thank you,'.PHP_EOL.
                                      '##your_site_name## Team');
register_activation_hook(__FILE__, 'SecondChance_first_run');

include 'functions.php';
include 'accept-offer.php';

function SecondChance_first_run() {
	$path = WP_PLUGIN_DIR . "/second-chance/config.txt";
	$fileContents = file_get_contents($path);
	$decoded = json_decode($fileContents, true);

	if($decoded["first_run"] === 0) {
		SecondChance_create_offer_page();
		SecondChance_create_table();
		$array         = array(
			'first_run'                => 1,
			'last_post_before_install' => 0,
			'last_post_checked'        => 0
		);
		$encodedString = json_encode($array);
		file_put_contents( $path, $encodedString );
	}
}
function register_second_chance_menu() {
    add_menu_page(
        'Second Chance',
        'Second Chance',
        'manage_options',
        'second-chance',
        'SecondChance_home_page',
        'dashicons-smiley');
}
function register_scripts() {
    wp_register_style('styles', plugins_url('css/styles.css', __FILE__) );
    wp_enqueue_style('styles');
	wp_register_script('scripts', plugins_url('js/scripts.js', __FILE__) );
	wp_enqueue_script('scripts');
}
function SecondChance_home_page() {
	global $wpdb;
	$posts_db = $wpdb->posts;
	$second_chance_db = $wpdb->prefix.'penny_second_chance';

	$postsPerPage = 8;
	if(isset($_GET['pj'])) {$page = $_GET['pj'];}
    else {$page = 1;}

    $query_for_all_pages = "
SELECT distinct posts.ID, sc.pid, sc.uid, sc.total_bids, sc.winner, sc.offered, sc.status
FROM $posts_db AS posts, $second_chance_db AS sc
WHERE posts.ID = sc.pid AND sc.offered = 1 AND sc.winner <> 1";
	
	$query_for_page = "
SELECT distinct posts.ID, posts.post_date, sc.pid, sc.uid, sc.total_bids, sc.winner, sc.offered, sc.status
FROM $posts_db AS posts, $second_chance_db AS sc
WHERE posts.ID = sc.pid AND sc.offered = 1 AND sc.winner <> 1 AND sc.status <= 1
ORDER BY posts.post_date DESC LIMIT ".($postsPerPage * ($page - 1) ).",".$postsPerPage;

	$query_for_orders = "
SELECT distinct posts.ID, posts.post_date, sc.pid, sc.uid, sc.total_bids, sc.winner, sc.offered, sc.status
FROM $posts_db AS posts, $second_chance_db AS sc
WHERE posts.ID = sc.pid AND sc.offered = 1 AND sc.winner <> 1 AND sc.status >= 2
ORDER BY posts.post_date DESC LIMIT ".($postsPerPage * ($page - 1) ).",".$postsPerPage;
	
	$total_num_offered = count($wpdb->get_results($query_for_all_pages));
	$my_page = $page;
	$current_page = $page;
	$totalPages = ($total_num_offered > 0 ? ceil($total_num_offered / $postsPerPage) : 0);
	$pages_s = $totalPages;

	$page_posts = $wpdb->get_results($query_for_page);
	$page_orders = $wpdb->get_results($query_for_orders);
	?>

    <div class="sc_wrapper">
        <header>
            <p id="sc_title">Second Chances Plugin</p>
        </header>
        <main>
            <div class="sc_navi">
                <ul>
                    <li><a href="#1" class="sc_link">Users Offered</a></li><li><a href="#2" class="sc_link">Paid Orders</a></li><li><a href="#3" class="sc_link">Settings</a></li>
                </ul>
            </div>
            <div class="sc_body_main">
                <div id="1" class="sc_tabs" style="display:block;">
                    <table class="widefat post fixed">
                        <thead>
                        <tr>
                            <th>Auction Title</th>
                            <th>Buyer</th>
                            <th>Total Bids</th>
                            <th>Cost</th>
                            <th>Discount</th>
                            <th>Shipping</th>
                            <th>Total</th>
                            <th>Purchase Date</th>
                            <th>Status</th>
                            <th style="display: none;">UID</th>
                            <th style="display: none;">PID</th>
                        </tr>
                        </thead>
                        <tbody>
	                        <?php $i = 0; if ($page_posts): ?>
                            <?php global $post; ?>
                            <?php foreach ($page_posts as $post): ?>
                            <?php setup_postdata($post); ?>
                            <?php
	                            $title      = get_the_title(get_the_ID());
	                            $uid        = $post->uid;
	                            $buyer      = get_user_by('ID',$post->uid)->user_nicename;
	                            $total_bids = $post->total_bids;
	                            $msrp       = get_post_meta(get_the_ID(),'retail_price',true);
	                            $total_wo   = SecondChance_calculate_discounted_price(get_the_ID(),$total_bids);
	                            $discount   = $msrp - $total_wo;
	                            $shp        = get_post_meta(get_the_ID(),'shipping',true); if(empty($shp)) {$shp = 0;}
	                            $total      = $total_wo + $shp;
	                            $msrp       = SecondChance_money_formatter($msrp);
	                            $discount   = SecondChance_money_formatter($discount);
	                            $shp        = SecondChance_money_formatter($shp);
	                            $total      = SecondChance_money_formatter($total);
	                            $date_choosen = $post->post_date;
	                            $status     = $post->status;
	                            switch($status) {
                                    case 0:
                                        $status = "Not";
                                        break;
                                    case 1:
	                                    $status = "Accepted";
                                        break;
                                    case 2:
	                                    $status = "Paid";
                                        break;
                                    case 3:
	                                    $status = "Shipped";
                                        break;
                                }

	                            ?>
                            <tr class="sc-tr">
                                <td><?php echo $title ?></td>
                                <td><?php echo $buyer ?></td>
                                <td><?php echo $total_bids ?></td>
                                <td><?php echo $msrp ?></td>
                                <td><?php echo $discount ?></td>
                                <td><?php echo $shp ?></td>
                                <td><?php echo $total ?></td>
                                <td><?php echo $date_choosen ?></td>
                                <td><select class="status" name="selector">
                                        <option hidden><?php echo $status ?></option>
                                        <option value="0">Not Accepted</option>
                                        <option value="1">Accepted</option>
                                        <option value="2">Paid</option>
                                        <option value="3">Shipped</option>
                                    </select></td>
                                <td class="nr1" style="display: none;"><?php echo $uid ?></td>
                                <td class="nr2" style="display: none;"><?php echo get_the_ID() ?></td>
                            </tr>
                            <?php endforeach; ?>
	                        <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="sc_nav">
		                <?php

		                $batch = 10;
		                $end = $batch * $postsPerPage;

		                if ($end > $pages_s) {$end = $pages_s;}
		                $start = $end - $postsPerPage + 1;
		                if($start < 1) {$start = 1;}
		                $links = '';

		                $report = ceil($my_page/$batch) - 1; if ($report < 0) {$report = 0;}

		                $start 		= $report * $batch + 1;
		                $end		= $start + $batch - 1;
		                $end_me 	= $end + 1;
		                $start_me 	= $start - 1;

		                if($end > $totalPages) {$end = $totalPages;}
		                if($end_me > $totalPages) {$end_me = $totalPages;}

		                if($start_me <= 0) {$start_me = 1;}

		                $previous_pg = $page - 1;
		                if($previous_pg <= 0) {$previous_pg = 1;}

		                $next_pg = $current_page + 1;
		                if($next_pg > $totalPages) {$next_pg = 1;}

		                if($my_page > 1)
		                {
			                echo '<a href="'.get_bloginfo('siteurl').'/wp-admin/admin.php?page=second-chance&pj='.$previous_pg.'"><< '.__('Previous','PennyTheme').'</a>';
			                echo '<a href="'.get_bloginfo('siteurl').'/wp-admin/admin.php?page=second-chance&pj=' .$start_me.'"><<</a>';
		                }

		                for($i = $start; $i <= $end; $i ++) {
			                if ($i == $current_page) {
				                echo '<a class="activee" href="#">'.$i.'</a>';
			                } else {

				                echo '<a href="'.get_bloginfo('siteurl').'/wp-admin/admin.php?page=second-chance&pj=' . $i.'" > '.$i.'</a>';

			                }
		                }

		                /*if($totalPages > $my_page)
		                {echo '<a href="'.get_bloginfo('siteurl').'/wp-admin/admin.php?page=second-chance&pj=' . $end_me.'">>></a>';}*/

		                if($page < $totalPages)
		                {echo '<a href="'.get_bloginfo('siteurl').'/wp-admin/admin.php?page=second-chance&pj=' . $next_pg.'"> '.__('Next','PennyTheme').' >></a>';}

		                ?>
                    </div>
                </div>
                <div id="2" class="sc_tabs">
                    <table class="widefat post fixed">
                        <thead>
                        <tr>
                            <th>Auction Title</th>
                            <th>Buyer</th>
                            <th>Total Bids</th>
                            <th>Cost</th>
                            <th>Discount</th>
                            <th>Shipping</th>
                            <th>Total</th>
                            <th>Purchase Date</th>
                            <th>Status</th>
                            <th style="display: none;">UID</th>
                            <th style="display: none;">PID</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $i = 0; if ($page_orders): ?>
		                <?php global $post; ?>
		                <?php foreach ($page_orders as $post): ?>
                            <?php setup_postdata($post); ?>
			                <?php
			                $title      = get_the_title(get_the_ID());
			                $uid        = $post->uid;
			                $buyer      = get_user_by('ID',$post->uid)->user_nicename;
			                $total_bids = $post->total_bids;
			                $msrp       = get_post_meta(get_the_ID(),'retail_price',true);
			                $total_wo   = SecondChance_calculate_discounted_price(get_the_ID(),$total_bids);
			                $discount   = $msrp - $total_wo;
			                $shp        = get_post_meta(get_the_ID(),'shipping',true); if(empty($shp)) {$shp = 0;}
			                $total      = $total_wo + $shp;
			                $msrp       = SecondChance_money_formatter($msrp);
			                $discount   = SecondChance_money_formatter($discount);
			                $shp        = SecondChance_money_formatter($shp);
			                $total      = SecondChance_money_formatter($total);
			                $date_choosen = $post->post_date;
			                $status     = $post->status;
			                switch($status) {
				                case 0:
					                $status = "Not";
					                break;
				                case 1:
					                $status = "Accepted";
					                break;
				                case 2:
					                $status = "Paid";
					                break;
				                case 3:
					                $status = "Shipped";
					                break;
			                }

			                ?>
                            <tr class="sc-tr">
                                <th><?php echo $title ?></th>
                                <th><?php echo $buyer ?></th>
                                <th><?php echo $total_bids ?></th>
                                <th><?php echo $msrp ?></th>
                                <th><?php echo $discount ?></th>
                                <th><?php echo $shp ?></th>
                                <th><?php echo $total ?></th>
                                <th><?php echo $date_choosen ?></th>
                                <td><select class="status" name="selector">
                                            <option hidden><?php echo $status ?></option>
                                            <option value="0">Not Accepted</option>
                                            <option value="1">Accepted</option>
                                            <option value="2">Paid</option>
                                            <option value="3">Shipped</option>
                                        </select></td>
                                <td class="nr1" style="display: none;"><?php echo $uid ?></td>
                                <td class="nr2" style="display: none;"><?php echo get_the_ID() ?></td>
                            </tr>
		                <?php endforeach; ?>
		                <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="sc_nav">
		                <?php

		                $batch = 10;
		                $end = $batch * $postsPerPage;

		                if ($end > $pages_s) {$end = $pages_s;}
		                $start = $end - $postsPerPage + 1;
		                if($start < 1) {$start = 1;}
		                $links = '';

		                $report = ceil($my_page/$batch) - 1; if ($report < 0) {$report = 0;}

		                $start 		= $report * $batch + 1;
		                $end		= $start + $batch - 1;
		                $end_me 	= $end + 1;
		                $start_me 	= $start - 1;

		                if($end > $totalPages) {$end = $totalPages;}
		                if($end_me > $totalPages) {$end_me = $totalPages;}

		                if($start_me <= 0) {$start_me = 1;}

		                $previous_pg = $page - 1;
		                if($previous_pg <= 0) {$previous_pg = 1;}

		                $next_pg = $current_page + 1;
		                if($next_pg > $totalPages) {$next_pg = 1;}

//		                if($my_page > 1)
//		                {
//			                echo '<a href="'.get_bloginfo('siteurl').'/wp-admin/admin.php?page=second-chance&pj='.$previous_pg.'"><< '.__('Previous','PennyTheme').'</a>';
//			                echo '<a href="'.get_bloginfo('siteurl').'/wp-admin/admin.php?page=second-chance&pj=' .$start_me.'"><<</a>';
//		                }

		                for($i = $start; $i <= $end; $i ++) {
			                if ($i == $current_page) {
				                echo '<a class="activee" href="#">'.$i.'</a>';
			                } else {

				                echo '<a style="margin: 0 4px;" href="'.get_bloginfo('siteurl').'/wp-admin/admin.php?page=second-chance&pj=' . $i.'" > '.$i.'</a>';

			                }
		                }

		                /*if($totalPages > $my_page)
		                {echo '<a style="margin: 0 4px;" href="'.get_bloginfo('siteurl').'/wp-admin/admin.php?page=second-chance&pj=' . $end_me.'">>></a>';}*/

		                if($page < $totalPages)
		                {echo '<a style="margin: 0 4px;" href="'.get_bloginfo('siteurl').'/wp-admin/admin.php?page=second-chance&pj=' . $next_pg.'">'.__('Next','PennyTheme').' >></a>';}

		                ?>
                    </div>
                </div>
                <div id="3" class="sc_tabs">
                    <p style="padding-left: 10px;">Version 1.0</p>
                    <div id="new_user_email">
                        <div><?php _e('This email will be received by you and when a user is offered a discounted rate.','PennyTheme'); ?> </div>
                        <form method="post" action="<?php bloginfo('siteurl');?>/wp-admin/admin.php?page=second-chance#3">
                            <table width="100%">
                                <tr>
                                    <td width="160"><?php _e('Email Subject:','PennyTheme'); ?></td>
                                    <td><input type="text" size="90" name="SecondChance_subject" value="<?php echo stripslashes(get_option('SecondChance_subject')); ?>"/></td>
                                </tr>
                                <tr>
                                    <td valign=top ><?php _e('Email Content:','PennyTheme'); ?></td>
                                    <td><textarea cols="92" rows="10" name="SecondChance_message"><?php echo stripslashes(get_option('SecondChance_message')); ?></textarea></td>
                                </tr>
                                <tr>
                                    <td valign=top ></td>
                                    <td><div class="spntxt_bo2">
							                <?php _e('Here is a list of tags you can use in this email:','PennyTheme'); ?><br/><br/>
                                            <strong>##username##</strong> - <?php _e("your new username",'PennyTheme'); ?><br/>
                                            <strong>##username_email##</strong> - <?php _e("your new user's email",'PennyTheme'); ?><br/>
                                            <strong>##your_site_name##</strong> - <?php _e("your website's name","PennyTheme"); ?><br/>
                                            <strong>##item_name##</strong> - <?php _e("auction item name","PennyTheme"); ?><br/>
                                            <strong>##item_link##</strong> - <?php _e("Provides the link for user to accept offer.","PennyTheme"); ?><br/>
                                            <strong>##item_price##</strong> - <?php _e("Total price after discount and shipping.",'PennyTheme'); ?>
                                        </div></td>
                                </tr>

                                <tr>
                                    <td ></td>
                                    <td><input type="submit" name="SecondChance-save-email" value="<?php _e('Save Options','PennyTheme'); ?>"/></td>
                                </tr>

                            </table>
                        </form>

                    </div>
                </div>
            </div>
        </main>
        <footer><h4>Created by Hannes Widrig</h4></footer>
    </div>

	<?php
}
function SecondChance_create_offer_page() {
    global $wpdb;
    $doesExist = $wpdb->get_results("SELECT * FROM wp_posts WHERE post_type = 'page' AND 
                              post_content = '[penny_theme_my_account_accept-offer]'");
    if(count($doesExist) === 0) {
	    $post = array(
		    'post_title' 	=> 'Accept Offer',
		    'post_content' 	=> '[penny_theme_my_account_accept-offer]',
		    'post_status' 	=> 'publish',
		    'post_type' 	=> 'page',
		    'post_author' 	=> 1,
		    'ping_status' 	=> 'closed',
		    'post_parent' 	=> get_option('PennyTheme_my_account_page_id'));

	    $post_id = wp_insert_post($post);
	    update_post_meta($post_id, '_wp_page_template', 'penny-special-page-template.php');
    }
}
function SecondChance_payment() {
	global $wp_query, $wp;
	$wp->add_query_var('pay_second');
	$a_action 	= $wp_query->query_vars['a_action'];
	if($a_action == "pay_second") {
//		include dirname(__FILE__).'/pay.php';
        include 'pay.php';
		exit;
	}
}

if(isset($_POST['SecondChance-save-email'])) {
	update_option('SecondChance_subject', 	trim($_POST['SecondChance_subject']));
	update_option('SecondChance_message', 	trim($_POST['SecondChance_message']));
	echo '<script language="javascript">';
	echo 'alert("Email updated!")';
	echo '</script>';
}

if(isset($_POST['action']) && !empty($_POST['action'])) {
	$action = $_POST['action'];
	switch($action) {
        case 'update-row':
	        $status = $_POST['status'];
	        $uid = $_POST['uid'];
	        $pid = $_POST['pid'];
            SecondChance_update_row($status, $uid, $pid);
            break;
	}
}



