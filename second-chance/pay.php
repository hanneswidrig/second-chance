<?php
session_start();
include 'paypal.class.php';

global $wp_query, $current_user, $wpdb;
$pid    = $wp_query->query_vars['pid'];
$uid = $_GET['user'];
$status    = $_GET['status'];
$action = $_GET['action'];

$p = new paypal_class;
$p->paypal_url = 'https://www.paypal.com/cgi-bin/webscr';   // testing paypal url
//$p->paypal_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';     // paypal url
$auctionTheme_enable_paypal_sandbox = get_option( 'PennyTheme_paypal_enable_sdbx' );
if ( $auctionTheme_enable_paypal_sandbox == "yes" ) {
	$p->paypal_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
}

$this_script = get_bloginfo( 'siteurl' ) . '/?action=pay_second&pid='.$pid.'&status='.$status.'&user='.$uid;
$paypal_email = get_option('PennyTheme_payPal_email');
$second_chance_db = $wpdb->prefix.'penny_second_chance';
$wpdb->query("UPDATE $second_chance_db SET status = $status WHERE pid = $pid AND uid = $uid");
$wpdb->query("UPDATE $second_chance_db SET accepted = $status WHERE pid = $pid AND uid = $uid");

if ( empty( $action ) ) {
	$action = 'process';
}
switch ( $action ) {
	case 'process':      // Process and order...
		$title = get_the_title($pid);
		$total = 0;
		$results = "SELECT sc.pid, sc.uid, sc.total_bids, sc.winner, sc.offered, sc.status FROM $second_chance_db AS sc
							 WHERE sc.pid = $pid AND sc.uid = $uid AND sc.winner <> 1";
		$poster = $wpdb->get_results($results);
		foreach ($poster as $post):
			setup_postdata($post);
			$total_bids = $post->total_bids;
			$msrp       = get_post_meta($pid,'retail_price',true);
			$total_wo   = SecondChance_calculate_discounted_price($pid,$total_bids);
			$discount   = $msrp - $total_wo;
			$shp        = get_post_meta($pid,'shipping',true); if(empty($shp)) {$shp = 0;}
			$total      = $total_wo + $shp;
		endforeach;
		$payTotal = $total;

		$p->add_field( 'business', $paypal_email );
		$p->add_field( 'currency_code', get_option( 'PennyTheme_currency' ) );
		$p->add_field( 'return', $this_script . '&action=success' );
		$p->add_field( 'cancel_return', $this_script . '&action=cancel' );
		$p->add_field( 'notify_url', $this_script . '&action=ipn' );
		$p->add_field( 'item_name', $title );
		$p->add_field( 'custom', $pid . '|' . current_time( 'timestamp', 0 ) . "|" . $uid );
		$p->add_field( 'amount', SecondChance_formats_special( $payTotal, 2 ) );

		$p->submit_paypal_post();
		break;
	case 'success':
	case 'ipn':
		if ( isset( $_POST['custom'] ) ) {
			global $current_user;
			$uid = $current_user->ID;

			$cust     = $_POST['custom'];
			$cust     = explode( "|", $cust );
			$pid      = $cust[0];
			$datemade = $cust[1];
			$uid      = $cust[2];
		}

		global $wpdb;
		$wpdb->query("UPDATE $second_chance_db set status = 2 WHERE pid = $pid AND uid = $uid");

		wp_redirect( get_permalink( get_option( 'PennyTheme_my_account_won_auctions_page_id' ) ) );
		exit;
		break;
	case 'cancel':       // Order was canceled...
		wp_redirect( get_permalink( get_option( 'PennyTheme_my_account_won_auctions_page_id' ) ) );
		exit;
		break;


}