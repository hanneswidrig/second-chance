<?php

add_action('wp_head', 'add_to_head');
function add_to_head() {
	wp_register_style('styles', plugins_url('css/accept-styles.css', __FILE__) );
	wp_enqueue_style('styles');
	wp_enqueue_style('wpb-google-fonts','https://fonts.googleapis.com/css?family=Muli:600', false);
	wp_register_script('scripts', plugins_url('js/scripts.js', __FILE__) );
	wp_enqueue_script('scripts');
}

function SecondChance_accept_offer_fncs() {
	ob_start();
	global $current_user;
	$uid = $current_user->ID;
	$pid = $_GET['pid'];
	?>
		<div class="sc-content">
			<div class="sc-auction-item">
				<?php

				global $wpdb;
				$second_chance_db = $wpdb->prefix.'penny_second_chance';
				$results = " SELECT sc.pid, sc.uid, sc.total_bids, sc.winner, sc.offered, sc.status FROM $second_chance_db AS sc
							 WHERE sc.pid = $pid AND sc.uid = $uid AND sc.winner <> 1";
				$poster = $wpdb->get_results($results);
				foreach ($poster as $post):
					setup_postdata($post);
					$title      = get_the_title($pid);
					$total_bids = $post->total_bids;
					$msrp       = get_post_meta($pid,'retail_price',true);
					$total_wo   = SecondChance_calculate_discounted_price($pid,$total_bids);
					$discount   = $msrp - $total_wo;
					$shp        = get_post_meta($pid,'shipping',true); if(empty($shp)) {$shp = 0;}
					$total      = $total_wo + $shp;
					$msrp       = SecondChance_money_formatter($msrp);
					$discount   = SecondChance_money_formatter($discount);
					$shp        = SecondChance_money_formatter($shp);
					$total      = SecondChance_money_formatter($total);
				endforeach;
				?>
				<div class="sc-image"><img src="<?php echo PennyTheme_get_first_post_image($pid,75,65); ?>"></div>
				<div class="sc-description"><p><?php echo $title ?></p></div>
			</div>
			<div class="sc-auction-pricing">
                <div>
                    <div id="sc-rt" class="auction-span"><span><?php echo $msrp ?></span><span class="sc-fr">Retail Price</span></div>
                    <div class="auction-span"><span>+</span><span><?php echo $shp ?></span><span class="sc-fr">Shipping</span></div>
                    <div class="auction-span"><span>-</span><span><?php echo $discount ?></span><span class="sc-fr">Your Discount</span><span></div>
                </div>
				<div id="sc-total"><span><?php echo $total ?></span></div>
			</div>
		</div>
		<div class="sc-accept">
			<div id="sc-button">
				<a href="<?php echo get_site_url().'/?a_action=pay_second&pid='.$pid.'&status=1' ?>">Accept Offer</a>
			</div>
		</div>
<?php
//	echo PennyTheme_get_users_links();
	$output = ob_get_contents();
	ob_end_clean();
	return $output;
}

add_shortcode('penny_theme_my_account_accept-offer','SecondChance_accept_offer_fncs');