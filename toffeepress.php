<?php
	/**
	 * Plugin Name: ToffeePress
	 * Plugin URI: https://toffeepress.twistphp.com/
	 * Description: Compress your images to a high quality allowing your pages to load faster, use less bandwidth, less storage and score better on general SEO tests.
	 * Version: 0.10.0
	 * Author: Dan Walker, James Durham
	 * Author URI: https://toffeepress.twistphp.com/about-us
	 * Text Domain: toffeepress
	 **/

	if(!defined('ABSPATH')){
		exit;
	}

	if(!class_exists('ToffeePress')){

		class ToffeePress{

			private static $strVersion = '0.10.0';
			public static $strStorageFolder = '';

			public function __construct() {
				register_activation_hook( __FILE__, array( $this, 'update_settings' ) );

				if(is_admin()){
					add_action('admin_menu', array(
						$this,
						'register_pages'
					));

					$this->update_settings();

					//Setup the AJAX request to process an attachment
					add_action('wp_ajax_nopriv_tp_remote_compress', array($this,'ajax_remote_compress'));
					add_action('wp_ajax_tp_remote_compress', array($this,'ajax_remote_compress'));

					add_action('wp_ajax_nopriv_tp_local_restore', array($this,'ajax_local_restore'));
					add_action('wp_ajax_tp_local_restore', array($this,'ajax_local_restore'));

					add_action('wp_ajax_nopriv_tp_cleanup', array($this,'ajax_cleanup'));
					add_action('wp_ajax_tp_cleanup', array($this,'ajax_cleanup'));

					wp_register_style('toffeepress-style',plugin_dir_url( __FILE__ ).'/css/toffeepress.css?'.self::$strVersion);
					wp_enqueue_style('toffeepress-style');

					wp_register_script('toffeepress-script',plugin_dir_url( __FILE__ ).'/js/toffeepress.js?'.self::$strVersion);
					wp_enqueue_script('toffeepress-script');
				}
			}

			public function load_textdomain() {
				//load_plugin_textdomain( 'toffeepress', false, 'toffeepress/lang' );
			}

			public function update_settings(){

				//Create some storage meta items
				add_option('toffeepress_api_key', '');
				add_option('toffeepress_quality', 'medium');
				add_option('toffeepress_keep_originals', '1');

				//Setup and create a storage folder
				$arrUploadPaths = wp_get_upload_dir();
				self::$strStorageFolder = $arrUploadPaths['basedir'].'/toffeepress';

				if(!is_dir(self::$strStorageFolder)){
					mkdir(self::$strStorageFolder.'/wp_original',0777,true);
				}
			}

			public function register_pages(){

				//Add the menu Item
				add_menu_page( 'ToffeePress', 'ToffeePress', 'manage_options', 'toffeepress', array($this,'page_overview'), plugin_dir_url( __FILE__ ).'/images/menu-icon.png', 70  );

				//Add the overview page menu item
				add_submenu_page('toffeepress','ToffeePress: Overview','Overview', 'manage_options', 'toffeepress', array($this,'page_overview'));

				//Add the settings page menu item
				add_submenu_page('toffeepress','ToffeePress: Settings','Settings', 'manage_options', 'toffeepress/settings', array($this,'page_settings'));

				//Add the cleanup page, this is not a menu item
				add_submenu_page(null,'Cleaning','Cleaning', 'manage_options', 'toffeepress/cleanup', array($this,'page_cleanup'));

				//Add the compressor page, this is not a menu item
				add_submenu_page(null,'Compressing','Compressing', 'manage_options', 'toffeepress/compress', array($this,'page_compress'));

				//Add the restore page, this is not a menu item
				add_submenu_page(null,'Restoring','Restoring', 'manage_options', 'toffeepress/restore', array($this,'page_restore'));
			}

			protected function _update_notice($strMessage,$blDismissible = false){
				echo '<div class="updated notice col-100'.(($blDismissible) ? 'is-dismissable' : '').'"><p>'.$strMessage.'</p></div>';
			}

			protected function _error_notice($strMessage,$blDismissible = false){
				echo '<div class="error notice col-100'.(($blDismissible) ? 'is-dismissable' : '').'"><p>'.$strMessage.'</p></div>';
			}

			public function page_overview(){

				$intSizeFiles = 0;
				$intTotalBytes = 0;
				$intTotalOriginalBytes = 0;
				$intTotalSaved = 0;
				$intTotalCompressed = 0;

				$intImageTotal = 0;
				$intImageCompressedTotal = 0;

				$arrSizes = array(
					'wp_original' => array('compressed' => 0, 'total' => 0,'backups' => 0)
				);

				foreach($this->getAttachments() as $arrEachPost){

					$arrMeta = $this->getAttachment($arrEachPost->ID);
					if(is_array($arrMeta)){

						$intSizeFiles += count($arrMeta['sizes']);

						$intImageTotal++;
						$arrSizes['wp_original']['total']++;

						if($arrMeta['original_exists']){
							$arrSizes['wp_original']['backups']++;
						}

						if($arrMeta['compressed']){
							$arrSizes['wp_original']['compressed']++;
							$intImageCompressedTotal++;
						}

						foreach($arrMeta['sizes'] as $strSize => $arrSizeData){

							if(!array_key_exists($strSize,$arrSizes)){
								$arrSizes[$strSize] = array('compressed' => 0, 'total' => 0);
							}

							$intImageTotal++;
							$arrSizes[$strSize]['total']++;
							if($arrSizeData['compressed']){
								$arrSizes[$strSize]['compressed']++;
								$intImageCompressedTotal++;
							}
						}

						$intTotalOriginalBytes += $arrMeta['bytes_total_original'];
						$intTotalBytes += $arrMeta['bytes_total'];
						$intTotalSaved += $arrMeta['bytes_saved'];
						$intTotalCompressed += $arrMeta['bytes_compressed'];
					}
				}

				$intPercentageSaved = number_format((100/$intTotalOriginalBytes) * $intTotalSaved,0,'.','');
				$intPercentageCompressed = number_format((100/$intImageTotal) * $intImageCompressedTotal,0,'.','');
				$intPercentageSmaller = number_format((100/$intTotalOriginalBytes) * $intTotalCompressed,0,'.','');

				$arrCredits = $this->getCredits();
				$blKeepOriginals = (get_option('toffeepress_keep_originals') == '1');
				?>
				<div class="wrap tp-flex-grid toffeepressWindow">

					<div class="tpHeader col-100-grid">
						<div class="logo"><img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/tp-logo-square.png" title="ToffeePress" alt="ToffeePress"></div>
						<div class="poweredBy">Powered by: <img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/twistphp.png" title="TwistPHP" alt="TwistPHP"></div>

						<div class="col-clear"></div>
						<div class="tpLiveStats col-100-grid">
							<div class="tpProgress col-50">
								<svg class="circle-chart" viewbox="0 0 33.83098862 33.83098862" width="180" height="180" xmlns="http://www.w3.org/2000/svg">
									<circle class="circle-chart__background" stroke="#EBEBEB" stroke-width="1" fill="none" cx="16.91549431" cy="16.91549431" r="15.91549431" />
									<circle class="circle-chart__circle" stroke="#00acc1" stroke-width="2" stroke-dasharray="<?php echo $intPercentageCompressed; ?>,100" stroke-linecap="round" fill="none" cx="16.91549431" cy="16.91549431" r="15.91549431" />
									<g class="circle-chart__info">
										<text class="circle-chart__percent" x="16.91549431" y="15.5" alignment-baseline="central" text-anchor="middle" font-size="8"><?php echo $intPercentageCompressed; ?>%</text>
										<text class="circle-chart__subline" x="16.91549431" y="22.5" alignment-baseline="central" text-anchor="middle" font-size="2"><?php echo number_format($intImageCompressedTotal,0,'',','); ?> of <?php echo number_format($intImageTotal,0,'',','); ?></text>
										<text class="circle-chart__subline" x="16.91549431" y="25" alignment-baseline="central" text-anchor="middle" font-size="2">Compressed</text>
									</g>
								</svg>

								<svg class="circle-chart" viewbox="0 0 33.83098862 33.83098862" width="180" height="180" xmlns="http://www.w3.org/2000/svg">
									<circle class="circle-chart__background" stroke="#EBEBEB" stroke-width="1" fill="none" cx="16.91549431" cy="16.91549431" r="15.91549431" />
									<circle class="circle-chart__circle" stroke="#00acc1" stroke-width="2" stroke-dasharray="<?php echo $intPercentageSaved; ?>,100" stroke-linecap="round" fill="none" cx="16.91549431" cy="16.91549431" r="15.91549431" />
									<g class="circle-chart__info">
										<text class="circle-chart__percent" x="16.91549431" y="15.5" alignment-baseline="central" text-anchor="middle" font-size="8"><?php echo $intPercentageSaved; ?>%</text>
										<text class="circle-chart__subline" x="16.91549431" y="22.5" alignment-baseline="central" text-anchor="middle" font-size="2">Smaller</text>
										<text class="circle-chart__subline" x="16.91549431" y="25" alignment-baseline="central" text-anchor="middle" font-size="2"><?php echo size_format($intTotalBytes,2); ?></span> Used!</text>
									</g>
								</svg>
							</div>
							<div class="tpStats col-50 no-stretch">
								<div class="infoBox">
									<div class="keyInfo"><span class="bytes_saved" data-bytes="0"><?php echo size_format($intTotalSaved,0); ?> Saved!</div>
									<div class="details">
										<?php echo ($arrCredits['allocated_credits'] > 0) ? number_format($arrCredits['allocated_credits'],0) :  number_format($arrCredits['credits'],0); ?> <?php echo ($arrCredits['results']['allocated_credits'] > 0) ? 'Allocated ' :  ''; ?> Credits<br>Latency <span class="ping"><?php echo $arrCredits['ping']; ?></span>, Original Size <?php echo size_format($intTotalOriginalBytes,2); ?>
										<?php
											if($blKeepOriginals){
												?><br>Original Backups: <?php echo $arrSizes['wp_original']['backups']; ?> of <?php echo $arrSizes['wp_original']['total']; ?>.<?php
											}else{
												?><br><span class="redText">Backups are currently disabled!</span><?php
											}
										?>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="tpBody col-100-grid">
						<form action="<?php echo get_admin_url().'admin.php?page=toffeepress/compress'; ?>" method="post">
							<p>Select the sizes that you would like to compress:</p>

							<table class="wp-list-table widefat fixed striped">
								<thead>
								<tr>
									<th></th>
									<th>Size</th>
									<th>Dimensions</th>
									<th>Crop</th>
									<th>Compressed</th>
									<th>Tools</th>
								</tr>
								</thead>
								<tbody>
								<?php
									$arrRegisteredSizes = $this->getImageSizes();
									foreach($arrSizes as $strSize => $arrCompressionData){
										$arrSizeInfo = (array_key_exists($strSize,$arrRegisteredSizes)) ? $arrRegisteredSizes[$strSize] : array('width' => '?','height' => '?','crop' => '?','registered' => false);
										?>
										<tr class="<?php echo ($arrCompressionData['compressed'] == $arrCompressionData['total']) ? 'compressedAll' : 'needsCompression';?>">
											<td><input type="checkbox" name="sizes[]" value="<?php echo $strSize; ?>"></td>
											<td><?php echo $strSize; ?></td>
											<td><?php echo $arrSizeInfo['width'].'x'.$arrSizeInfo['height']; ?></td>
											<td><?php echo ($arrSizeInfo['crop'] > 0) ? 'Yes' : 'No'; ?></td>
											<td class="stats"><?php echo $arrCompressionData['compressed'].' / '.$arrCompressionData['total']; ?></td>
											<td class="tools">
												<?php echo ($strSize == 'wp_original' || $arrSizeInfo['registered']) ? '' : '<a href="'.get_admin_url().'admin.php?page=toffeepress/cleanup&size='.$strSize.'">Clean Up</a>'; ?>
												<?php echo ($blKeepOriginals) ? '<a href="'.get_admin_url().'admin.php?page=toffeepress/restore&sizes[]='.$strSize.'">Restore</a>' : ''; ?>
											</td>
										</tr>
									<?php }
								?>
								</tbody>
							</table>
							<br>
							<button type="submit" class="tpButton blue" <?php echo ($blKeepOriginals) ? '' : 'onClick="return confirm(\'Are you sure you want to continue? You have the Keep Originals backup setting turned off!\');"'; ?>>Compress</button>
						</form>
					</div>
				</div>
				<?php
			}

			public function page_cleanup(){

				$arrCleanupList = array();
				if(!array_key_exists('size',$_GET)){
					echo '<p>Error, you must use the compression form to get to this page!</p><a href="'.get_admin_url().'admin.php?page=toffeepress">Back</a>';
					die();
				}

				$arrPosts = $this->getAttachments();

				foreach($arrPosts as $arrEachPost){
					$arrMeta = $this->getAttachment($arrEachPost->ID);

					foreach($arrMeta['sizes'] as $strSize => $arrSizeData){
						if($strSize == sanitize_text_field($_GET['size'])){
							$arrCleanupList[] = array('id' => $arrEachPost->ID,'s' => $strSize);
						}
					}
				}
				?>
				<script>
					var tp_admin_ajax = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
					var tp_cleanup_list = <?php echo json_encode($arrCleanupList); ?>;

					jQuery(document).ready(function() {
						tpStartCleanupProcess();
					});
				</script>
				<div class="wrap tp-flex-grid toffeepressWindow">
					<h1><img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/tp-logo.png" title="ToffeePress" alt="ToffeePress"> Cleaning ...</h1>
					<div class="col-clear"></div>
					<p>Stats on the files being removed in this run</p>
					<div class="progressBox">
						<div>Removing size <span class="sizes"><?php echo esc_html($_GET['size']); ?></span></div>
						<div>Files to remove <span class="files_to_cleanup"><?php echo count($arrCleanupList); ?></span></div>
						<div>Files removed <span class="files_cleaned">0</span></div>
						<div>Cleaning <span class="cleaning">0</span> file(s)</div>
					</div>
					<a href="#" onclick="tpStopCleanupProcess();">Stop Process</a>
					<div class="log"></div>
					<div class="poweredBy">Powered by: <img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/twistphp.png" title="TwistPHP" alt="TwistPHP"></div>
				</div>
				<?php
			}

			public function ajax_cleanup(){

				$arrResponse = array('status' => false,'message' => 'Missing AJAX POST parameters');

				if(array_key_exists('attachment',$_POST)){
					if(is_array($_POST['attachment']) && count($_POST['attachment'])){

						foreach($_POST['attachment'] as $strItem){
							list($intAttachmentID,$strSize) = explode(',',$strItem);
							$arrResponse = $this->deleteAttachmentSize((int) $intAttachmentID,sanitize_text_field($strSize));
						}
					}
				}

				ob_end_clean();

				//Set the response type as json
				header("Content-type: application/json");

				echo json_encode($arrResponse);
				die();
			}

			public function page_compress(){

				$arrCredits = $this->getCredits();
				if($arrCredits['status']){

					//POST Sizes is an array
					$arrCompressSizes = (array_key_exists('sizes',$_POST)) ? $_POST['sizes'] : array();
					$arrCompressList = array();

					if(is_array($arrCompressSizes) && count($arrCompressSizes)){

						$arrPosts = $this->getAttachments();

						foreach($arrPosts as $arrEachPost){
							$arrMeta = $this->getAttachment($arrEachPost->ID);

							if(!is_array($arrMeta) || $arrMeta['all_compressed']){
								continue;
							}

							if(in_array('wp_original',$arrCompressSizes) && !$arrMeta['compressed']){
								//Add the original attachment to the list
								$arrCompressList[] = array('id' => $arrEachPost->ID,'s' => 'wp_original');
							}

							foreach($arrMeta['sizes'] as $strSize => $arrSizeData){
								if(in_array($strSize,$arrCompressSizes) && !$arrSizeData['compressed']){
									$arrCompressList[] = array('id' => $arrEachPost->ID,'s' => $strSize);
								}
							}
						}
						?>
						<script>
							var tp_admin_ajax = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
							var tp_compress_list = <?php echo json_encode($arrCompressList); ?>;
							var tp_files_to_compress = <?php echo count($arrCompressList); ?>;
							var tp_files_compressed = 0;
							var tp_files_failed = 0;
							var tp_currently_compressing = 0;
							var tp_bytes_saved = 0;
							var tp_time_started = new Date().getTime() / 1000;
							var tp_time_remaining = 0;
							var tp_type = 'tp_remote_compress';

							jQuery(document).ready(function() {
								tpStartProcess();
							});
						</script>
						<div class="wrap tp-flex-grid toffeepressWindow">
							<div class="tpHeader col-100-grid">
								<div class="logo"><img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/tp-logo-square.png" title="ToffeePress" alt="ToffeePress"></div>
								<div class="poweredBy">Powered by: <img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/twistphp.png" title="TwistPHP" alt="TwistPHP"></div>

								<div class="col-clear"></div>
								<div class="tpLiveStats col-100-grid">
									<div class="tpProgress col-50">
										<svg class="circle-chart" viewbox="0 0 33.83098862 33.83098862" width="180" height="180" xmlns="http://www.w3.org/2000/svg">
											<circle class="circle-chart__background" stroke="#EBEBEB" stroke-width="1" fill="none" cx="16.91549431" cy="16.91549431" r="15.91549431" />
											<circle id="percentageStroke" class="circle-chart__circle" stroke="#00acc1" stroke-width="2" stroke-dasharray="0,100" stroke-linecap="round" fill="none" cx="16.91549431" cy="16.91549431" r="15.91549431" />
											<g class="circle-chart__info">
												<text id="percentageTextbox1" class="circle-chart__percent" x="16.91549431" y="15.5" alignment-baseline="central" text-anchor="middle" font-size="8">0%</text>
												<text id="percentageTextbox2" class="circle-chart__subline" x="16.91549431" y="22.5" alignment-baseline="central" text-anchor="middle" font-size="2">0 of <?php echo number_format(count($arrCompressList),0,'',','); ?></text>
												<text id="percentageTextbox3" class="circle-chart__subline" x="16.91549431" y="25" alignment-baseline="central" text-anchor="middle" font-size="2">Compressed</text>
											</g>
										</svg>
									</div>
									<div class="tpStats col-50 no-stretch">
										<div class="infoBox">
											<div class="keyInfo"><span class="bytes_saved" data-bytes="0">0</span> saved!</div>
											<div class="details"><span class="remainingTime">0</span> Remaining<br>Latency <span class="ping"><?php echo $arrCredits['ping']; ?></span>, Compressing <span class="compressing">0</span> file(s)</div>
											<div class="buttons">
												<a href="#" onclick="tpStopProcess();" class="tpButton red cancel" style="display: none;">Stop Process</a>
												<a href="<?php echo admin_url('admin.php?page=toffeepress'); ?>" class="tpButton white finished">Finish</a>
											</div>
										</div>
									</div>
								</div>
							</div>
							<div class="tpBody col-100-grid log">

							</div>
						</div>
						<?php
					}else{
						echo '<div class="wrap toffeepressWindow"><h1><img src="'.plugin_dir_url( __FILE__ ).'/images/tp-logo.png" title="ToffeePress" alt="ToffeePress"> Error</h1><p>Error, you must use the compression form to get to this page!</p><a href="'.get_admin_url('admin.php?page=toffeepress').'" class="tpButton red">Back</a></div>';
						die();
					}
				}else{
					echo '<div class="wrap toffeepressWindow"><h1><img src="'.plugin_dir_url( __FILE__ ).'/images/tp-logo.png" title="ToffeePress" alt="ToffeePress"> Error</h1><p>'.$arrCredits['message'].'</p><p>You can register for a FREE API key (500 credits/month) or you can top your credits at <a href="https://toffeepress.twistphp.com" target="_blank">https://toffeepress.twistphp.com</a>.</p><a href="'.get_admin_url('admin.php?page=toffeepress').'" class="tpButton red">Back</a></div>';
					die();
				}
			}

			public function ajax_remote_compress(){

				$arrResponse = array('status' => false,'message' => 'Missing AJAX POST parameters','saving_bytes' => 0);

				if(array_key_exists('attachment',$_POST) && array_key_exists('size',$_POST)){
					//Type cast the attachment ID, integer input only and sanitise the size string
					$arrResponse = $this->compressImage((int) $_POST['attachment'],sanitize_text_field($_POST['size']));
				}

				ob_end_clean();

				//Set the response type as json
				header("Content-type: application/json");

				echo json_encode($arrResponse);
				die();
			}

			public function page_restore(){

				//POST Sizes is an array
				$arrCompressSizes = (array_key_exists('sizes',$_REQUEST)) ? $_REQUEST['sizes'] : array();
				$arrCompressList = array();

				if(is_array($arrCompressSizes) && count($arrCompressSizes)){

					$arrPosts = $this->getAttachments();

					foreach($arrPosts as $arrEachPost){
						$arrMeta = $this->getAttachment($arrEachPost->ID);

						if(!is_array($arrMeta)){
							continue;
						}

						if(in_array('wp_original',$arrCompressSizes) && $arrMeta['compressed']){
							//Add the original attachment to the list
							$arrCompressList[] = array('id' => $arrEachPost->ID,'s' => 'wp_original');
						}

						foreach($arrMeta['sizes'] as $strSize => $arrSizeData){
							if(in_array($strSize,$arrCompressSizes) && $arrSizeData['compressed']){
								$arrCompressList[] = array('id' => $arrEachPost->ID,'s' => $strSize);
							}
						}
					}
					?>
					<script>
						var tp_admin_ajax = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
						var tp_compress_list = <?php echo json_encode($arrCompressList); ?>;
						var tp_files_to_compress = <?php echo count($arrCompressList); ?>;
						var tp_files_compressed = 0;
						var tp_files_failed = 0;
						var tp_currently_compressing = 0;
						var tp_bytes_saved = 0;
						var tp_time_started = new Date().getTime() / 1000;
						var tp_time_remaining = 0;
						var tp_type = 'tp_local_restore';

						jQuery(document).ready(function() {
							tpStartProcess();
						});
					</script>
					<div class="wrap tp-flex-grid toffeepressWindow">
						<div class="tpHeader col-100-grid">
							<div class="logo"><img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/tp-logo-square.png" title="ToffeePress" alt="ToffeePress"></div>
							<div class="poweredBy">Powered by: <img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/twistphp.png" title="TwistPHP" alt="TwistPHP"></div>

							<div class="col-clear"></div>
							<div class="tpLiveStats col-100-grid">
								<div class="tpProgress col-50">
									<svg class="circle-chart" viewbox="0 0 33.83098862 33.83098862" width="180" height="180" xmlns="http://www.w3.org/2000/svg">
										<circle class="circle-chart__background" stroke="#EBEBEB" stroke-width="1" fill="none" cx="16.91549431" cy="16.91549431" r="15.91549431" />
										<circle id="percentageStroke" class="circle-chart__circle" stroke="#00acc1" stroke-width="2" stroke-dasharray="0,100" stroke-linecap="round" fill="none" cx="16.91549431" cy="16.91549431" r="15.91549431" />
										<g class="circle-chart__info">
											<text id="percentageTextbox1" class="circle-chart__percent" x="16.91549431" y="15.5" alignment-baseline="central" text-anchor="middle" font-size="8">0%</text>
											<text id="percentageTextbox2" class="circle-chart__subline" x="16.91549431" y="22.5" alignment-baseline="central" text-anchor="middle" font-size="2">0 of <?php echo number_format(count($arrCompressList),0,'',','); ?></text>
											<text id="percentageTextbox3" class="circle-chart__subline" x="16.91549431" y="25" alignment-baseline="central" text-anchor="middle" font-size="2">Restored</text>
										</g>
									</svg>
								</div>
								<div class="tpStats col-50 no-stretch">
									<div class="infoBox">
										<div class="keyInfo"><span class="bytes_saved" data-bytes="0">0</span> expanded!</div>
										<div class="details"><span class="remainingTime">0</span> Remaining<br>Restoring <span class="compressing">0</span> file(s)</div>
										<div class="buttons">
											<a href="#" onclick="tpStopProcess();" class="tpButton red cancel" style="display: none;">Stop Process</a>
											<a href="<?php echo admin_url('admin.php?page=toffeepress'); ?>" class="tpButton white finished">Finish</a>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="tpBody col-100-grid log">

						</div>
					</div>
					<?php
				}else{
					echo '<div class="wrap toffeepressWindow"><h1><img src="'.plugin_dir_url( __FILE__ ).'/images/tp-logo.png" title="ToffeePress" alt="ToffeePress"> Error</h1><p>Error, you must use the compression form to get to this page!</p><a href="'.get_admin_url('admin.php?page=toffeepress').'" class="tpButton red">Back</a></div>';
					die();
				}
			}

			public function ajax_local_restore(){

				$arrResponse = array('status' => false,'message' => 'Missing AJAX POST parameters','saving_bytes' => 0);

				if(array_key_exists('attachment',$_POST) && array_key_exists('size',$_POST)){
					//Type cast the attachment ID, integer input only and sanitise the size string
					$arrResponse = $this->restoreOriginal((int) $_POST['attachment'],sanitize_text_field($_POST['size']));
				}

				ob_end_clean();

				//Set the response type as json
				header("Content-type: application/json");

				echo json_encode($arrResponse);
				die();
			}

			public function page_settings(){
                if(array_key_exists('toffeepress_email',$_POST)){
                    $arrResults = $this->registerAccount($_POST['toffeepress_email'],$_POST['toffeepress_first_name'],$_POST['toffeepress_last_name']);
                    if($arrResults['status']){
						update_option('toffeepress_api_key',sanitize_text_field($arrResults['api_key']));
						$this->_update_notice('Thank you for registering! your API key has been applied.');
                    } else{
                        $this->_error_notice($arrResults['message']);
                    }
                }

				if(array_key_exists('toffeepress_api_key',$_POST) || array_key_exists('toffeepress_quality',$_POST) || array_key_exists('toffeepress_keep_originals',$_POST)){
					update_option('toffeepress_api_key',sanitize_text_field($_POST['toffeepress_api_key']));
					update_option('toffeepress_quality',sanitize_text_field($_POST['toffeepress_quality']));
					update_option('toffeepress_keep_originals',sanitize_text_field($_POST['toffeepress_keep_originals']));
					$this->_update_notice('API Key and Compression settings successfully saved');
				}

				$arrCredits = $this->getCredits();

				?>
				<div class="wrap tp-flex-grid toffeepressWindow">
					<div class="tpHeader col-100-grid">
						<div class="logo"><img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/tp-logo-square.png" title="ToffeePress" alt="ToffeePress"></div>
						<div class="poweredBy">Powered by: <img src="<?php echo plugin_dir_url( __FILE__ ); ?>/images/twistphp.png" title="TwistPHP" alt="TwistPHP"></div>

						<div class="col-clear"></div>
						<div class="tpLiveStats col-100-grid">

						</div>
					</div>
					<div class="tpBody col-100-grid">
						<div class="col-clear"></div>
						<?php if(get_option('toffeepress_api_key') == ''){?>
							<div class="col-50 tpSettingsBox">
								<div class="title">
									<h3>Register for API key</h3>
								</div>
								<div class="inner">
									<form action="<?php echo get_admin_url().'admin.php?page=toffeepress/settings'; ?>" method="post">
										<p>Dont have an API key? Register for an account today and get 500 free credits a month!</p>
										<table class="form-table">
											<tbody>
											<tr>
												<th><label for="toffeepress_email">Email</label></th>
												<td>
													<div class="input-wrap">
														<input style="width:100%;" type="email" id="toffeepress_email" name="toffeepress_email" value="" />
													</div>
												</td>
											</tr>
											<tr>
												<th><label for="toffeepress_first_name">Name</label></th>
												<td>
													<div class="input-wrap">
														<input style="width:100%;" type="text" id="toffeepress_first_name" name="toffeepress_first_name" />
													</div>
												</td>
											</tr>
											<tr>
												<th><label for="toffeepress_last_name">Last Name</label></th>
												<td>
													<div class="input-wrap">
														<input style="width:100%;" type="text" id="toffeepress_last_name" name="toffeepress_last_name" />
													</div>
												</td>
											</tr>
											</tbody>
										</table>
										<button type="submit" class="tpButton">Register</button>
									</form>
								</div>
							</div>
							<div class="col-50"></div>
							<div class="col-clear"></div>
						<?php } ?>
						<div class="col-50 tpSettingsBox">
							<div class="title">
								<h3>API Key Setup</h3>
							</div>
							<div class="inner">
								<p>To connect up the plugin to the ToffeePress service you need to enter your API key. You can register for a FREE API key (500 credits/month) or you can top your credits at <a href="https://toffeepress.twistphp.com" target="_blank">https://toffeepress.twistphp.com</a>.</p>
								<form action="<?php echo get_admin_url().'admin.php?page=toffeepress/settings'; ?>" method="post">
									<table class="form-table">
										<tbody>
										<tr>
											<th><label for="toffeepress_api_key">API Key</label></th>
											<td>
												<div class="input-wrap">
													<input style="width:100%;" type="text" id="toffeepress_api_key" name="toffeepress_api_key" value="<?php echo get_option('toffeepress_api_key'); ?>" />
												</div>
											</td>
										</tr>
										<tr>
											<th><label for="toffeepress_quality">Compression Quality</label></th>
											<td>
												<div class="input-wrap">
													<select style="width:100%;" id="toffeepress_quality" name="toffeepress_quality">
														<option value="high" <?php echo (get_option('toffeepress_quality') == 'high') ? 'selected' : ''; ?>>High Quality (Small savings, better image quality)</option>
														<option value="medium" <?php echo (get_option('toffeepress_quality') == '' || get_option('toffeepress_quality') == 'medium') ? 'selected' : ''; ?>>Medium Quality (Balance between compression and quality)</option>
														<option value="low" <?php echo (get_option('toffeepress_quality') == 'low') ? 'selected' : ''; ?>>Low Quality (Higher image compression)</option>
													</select>
												</div>
											</td>
										</tr>
										<tr>
											<th><label for="toffeepress_keep_originals">Keep Originals</label></th>
											<td>
												<div class="input-wrap">
													<select style="width:100%;" id="toffeepress_keep_originals" name="toffeepress_keep_originals">
														<option value="1" <?php echo (get_option('toffeepress_keep_originals') == '' || get_option('toffeepress_keep_originals') == '1') ? 'selected' : ''; ?>>Yes</option>
														<option value="0" <?php echo (get_option('toffeepress_keep_originals') == '0') ? 'selected' : ''; ?>>No</option>
													</select>
												</div>
												<p>Keeping a copy of the original full sized images, when enabled you can reverse the compression of any image. Extra storage will be used to keep the original files. Backups are created automatically during compression.</p>
											</td>
										</tr>
										</tbody>
									</table>
									<button type="submit" class="tpButton">Save</button>
								</form>
							</div>
						</div>
						<div class="col-50 tpSettingsBox">
							<div class="title">
								<h3>Account Information</h3>
							</div>
							<div class="inner">
								<p>Your account information is pulled though via your API key, you can find how many credits you have remaining and some other interesting information.</p>
								<form action="<?php echo get_admin_url().'admin.php?page=toffeepress/settings'; ?>" method="post">
									<table class="form-table">
										<tbody>
										<tr>
											<th><label>Your Credits</label></th>
											<td><div class="input-wrap">You have <?php echo ($arrCredits['allocated_credits'] > 0) ? number_format($arrCredits['allocated_credits'],0) :  number_format($arrCredits['credits'],0); ?> <?php echo ($arrCredits['results']['allocated_credits'] > 0) ? 'allocated ' :  ''; ?>compression credits</div></td>
										</tr>
										<tr>
											<th><label>Key Name</label></th>
											<td><div class="input-wrap"><?php echo ($arrCredits['title'] == '') ? 'Untitled' : $arrCredits['title']; ?></div></td>
										</tr>
										<tr>
											<th><label>Latency</label></th>
											<td><div class="input-wrap"><?php echo $arrCredits['ping']; ?></div></td>
										</tr>
										</tbody>
									</table>
								</form>
							</div>
						</div>
					</div>
				</div>
				<?php
			}

			public function backupOriginal($intAttachmentID){

				$arrResponse = array(
					'status' => false,
					'message' => ''
				);

				if(!$this->originalExists($intAttachmentID)){
					if(is_dir(self::$strStorageFolder.'/wp_original')){

						$arrAttachment = $this->getAttachment($intAttachmentID);
						if(is_array($arrAttachment)){

							//Make sure the /year/month folder has been created in the wp_original dir
							mkdir(dirname($arrAttachment['original_file']),0777,true);

							//Commented out until WP fixes the missing dirlist() function that causes a Fatal Error
							//copy_dir($arrAttachment['file'],$arrAttachment['original_file']);
							copy($arrAttachment['file'],$arrAttachment['original_file']);

							$arrResponse['status'] = true;
							$arrResponse['message'] = 'OK';

							return $arrResponse;
						}else{
							$arrResponse['message'] = 'Invalid Attachment ID '.$intAttachmentID.' provided';
						}
					}else{
						$arrResponse['message'] = 'ToffeePress wp_original backup doesnt exist';
					}
				}else{
					$arrResponse['message'] = 'Original backup of this attachment already exists';
				}

				return $arrResponse;
			}

			public function restoreOriginal($intAttachmentID,$strSize = 'wp_original'){

				$arrResponse = array(
					'status' => false,
					'message' => 'OK',
					'original_bytes' => 0,
					'destination_bytes' => 0,
					'saving_bytes' => 0,
					'saving_percentage' => 0
				);

				if($this->originalExists($intAttachmentID)){
					$arrAttachment = $this->getAttachment($intAttachmentID);

					if(is_array($arrAttachment)){

						if($strSize == 'wp_original'){

							$arrResponse['original_bytes'] = filesize($arrAttachment['file']);
							$arrResponse['destination_bytes'] = filesize($arrAttachment['original_file']);

							//If it is an original file directly replace it, remove current original and copy backup original into its place
							unlink($arrAttachment['file']);
							//Commented out until WP fixes the missing dirlist() function that causes a Fatal Error
							//copy_dir($arrAttachment['original_file'],$arrAttachment['file']);
							copy($arrAttachment['original_file'],$arrAttachment['file']);
							$arrResponse['status'] = true;
						}else{
							//If it is a smaller size, re-create it from the backed up original
							$arrResponse['original_bytes'] = filesize($arrAttachment['sizes'][$strSize]['file']);

							$arrResult = $this->regenerateAttachmentSize($intAttachmentID,$strSize,$arrAttachment['original_file']);
							$arrResponse['status'] = $arrResult['status'];
							$arrResponse['message'] = $arrResult['message'];

							$arrResponse['destination_bytes'] = filesize($arrAttachment['sizes'][$strSize]['file']);
						}

						$arrResponse['saving_bytes'] = ($arrResponse['destination_bytes'] - $arrResponse['original_bytes']);

						//Remove the compression meta data as the original is now
						delete_post_meta($intAttachmentID, '_tp_compressed_'.strtolower($strSize));

						return $arrResponse;
					}else{
						$arrResponse['message'] = 'Invalid Attachment ID '.$intAttachmentID.' provided';
					}
				}else{
					$arrResponse['message'] = 'Cannot be restore as there is no original backup of this attachment';
				}

				return $arrResponse;
			}

			public function originalExists($intAttachmentID){
				$arrAttachment = $this->getAttachment($intAttachmentID);
				return (count($arrAttachment)) ? $arrAttachment['original_exists'] : false;
			}

			public function compressImage($intAttachmentID,$strSize = 'wp_original'){

				$arrResultData = array('status' => false,'message' => 'Invalid Attachment or File data','saving_bytes' => 0);
				$arrSizes = $this->getImageSizes();

				//Check that a valid size has been requested
				if(array_key_exists($strSize,$arrSizes)){

					$arrAttachment = $this->getAttachment($intAttachmentID);
					if(is_array($arrAttachment) && count($arrAttachment) && array_key_exists('tp_meta',$arrAttachment) && !array_key_exists('_tp_compressed_'.strtolower($strSize),$arrAttachment['tp_meta'])){

						$strFileURL = ($strSize == 'wp_original') ? $arrAttachment['url'] : $arrAttachment['sizes'][$strSize]['url'];
						$strFilePath = ($strSize == 'wp_original') ? $arrAttachment['file'] : $arrAttachment['sizes'][$strSize]['file'];

						if(!empty($strFilePath) && !file_exists($strFilePath)){
							$this->regenerateAttachmentSize($intAttachmentID,$strSize);
						}

						//If Keep Originals has been set and this is an original image, take a copy
						if($strSize == 'wp_original' && get_option('toffeepress_keep_originals') == '1'){
							$this->backupOriginal($intAttachmentID);
						}

						if(!empty($strFileURL) && !empty($strFilePath) && file_exists($strFilePath)){

							//Send the request to have the file compressed
							$mxdResponse = wp_remote_post('https://toffeepress.twistphp.com/api/compress', array(
								'timeout' => 20,
								'headers' => array(
									'Auth-Key' => get_option('toffeepress_api_key'),
									'Auth-Referrer' => get_site_url()
								),
								'body' => array(
									'image' => $strFileURL,
									'quality' => get_option('toffeepress_quality')
								)
							));

							$arrResult = json_decode($mxdResponse['body'],true);
							if(is_array($arrResult) && array_key_exists('status',$arrResult)){

								if($arrResult['status'] == 'success'){

									$arrResultData = $arrResult['results'];
									if($arrResultData['status']){

										//Download the compressed image file
										file_put_contents($strFilePath,wp_remote_get($arrResultData['url'], array(
											'headers' => array(
												'Auth-Key' => get_option('toffeepress_api_key'),
												'Auth-Referrer' => get_site_url()
											)
										))['body']);

										//Log Meta Data
										update_post_meta($intAttachmentID, '_tp_compressed_'.strtolower($strSize), array(
											'bytes_original' => $arrResultData['original_bytes'],
											'bytes_compressed' => $arrResultData['destination_bytes'],
											'percent_saved' => $arrResultData['saving_percentage'],
											'quality' => get_option('toffeepress_quality'),
											'compressed' => time()
										));
									}
								}else{
									$arrResultData['message'] = $arrResult['error'];
								}
							}else{
								$arrResultData['message'] = 'Incorrectly formatted response from remote server';
							}
						}else{
							$arrResultData['message'] = "File not found, couldn't be compressed: {$strFilePath}";
						}
					}else{
						$arrResultData['message'] = 'The attachment '.$intAttachmentID.' ['.$strSize.'] has already been compressed';
					}
				}else{
					$arrResultData['message'] = 'Invalid image size ['.$strSize.'] requested';
				}

				return $arrResultData;
			}

			public function getCredits(){

				if(get_option('toffeepress_api_key') != ''){

					//Send the request to have the file compressed
					$ftlStartTime = microtime(true);

					//Request the credit level for this account
					$mxdResponse = wp_remote_get('https://toffeepress.twistphp.com/api/credits', array(
						'headers' => array(
							'Auth-Key' => get_option('toffeepress_api_key'),
							'Auth-Referrer' => get_site_url()
						)
					));

					$intLatency = round((microtime(true)-$ftlStartTime)*1000);
					$arrResult = json_decode($mxdResponse['body'],true);
					$arrResult['results']['ping'] = $intLatency.'ms';

					return $arrResult['results'];
				}

				return array('title' => '','credits' => 0,'allocated_credits' => 0,'status' => false, 'message' => 'No API key has been setup, go to ToffeePress settings to finish the setup process.');
			}

			public function registerAccount($strEmail,$strFirstname,$strLastname){

					//Send the request to have the file compressed
					$ftlStartTime = microtime(true);

					//Request the credit level for this account
					$mxdResponse = wp_remote_post('https://toffeepress.twistphp.com/api/register', array(
						'headers' => array(
							'Auth-Key' => 'WAUD6QVTRBW7ZKQPDCQWKDTE',//Developer API Key (Functional only, no credits assigned)
							'Auth-Referrer' => get_site_url()
						),
						'body' => array(
							'email' => $strEmail,
							'first_name' => $strFirstname,
							'last_name' => $strLastname
						)
					));

					$intLatency = round((microtime(true)-$ftlStartTime)*1000);
					$arrResult = json_decode($mxdResponse['body'],true);
					$arrResult['results']['ping'] = $intLatency.'ms';
                    if($arrResult['status'] == 'success'){
						return array('status' => true, 'message' => '', 'api_key' => $arrResult['results']['api_key']);
                    }
				    return array('status' => false, 'message' => $arrResult['error'], 'api_key' => '');
			}

			public function getAttachments(){

				$arrArgs = array(
					'post_type' => 'attachment',
					'post_mime_type' =>array(
						'jpg|jpeg|jpe' => 'image/jpeg',
						'gif' => 'image/gif',
						'png' => 'image/png',
					),
					'post_status' => 'inherit',
					'posts_per_page' => -1,
				);

				$resResult = new WP_Query($arrArgs);

				return (is_object($resResult)) ? $resResult->posts : array();
			}

			public function getAttachmentCount(){
				return count($this->getAttachments());
			}

			public function getAttachment($intAttachmentID){

				$blAllCompressed = true;
				$intBytesTotal = 0;
				$intBytesTotalCompressed = 0;
				$intBytesTotalOriginal = 0;
				$intBytesSaved = 0;

				$arrUploadPaths = wp_get_upload_dir();
				$arrAttachment = wp_get_attachment_metadata($intAttachmentID,false);

				//False is returned upon failure
				if(is_array($arrAttachment)){

					$arrAttachment['tp_meta'] = array();
					foreach(get_post_meta($intAttachmentID) as $strKey => $mxdData){
						if(substr($strKey,0,4) == '_tp_'){
							$arrAttachment['tp_meta'][$strKey] = unserialize($mxdData[0]);
						}
					}

					$arrPathParts = explode('/',$arrAttachment['file']);
					array_pop($arrPathParts);
					$strContentPath = implode('/',$arrPathParts);

					//Do the URL first otherwise will run into issues with URL generation
					$strBackupFile = self::$strStorageFolder.'/wp_original/'.$arrAttachment['file'];
					$arrAttachment['url'] = $arrUploadPaths['baseurl'].'/'.$arrAttachment['file'];
					$arrAttachment['file'] = $arrUploadPaths['basedir'].'/'.$arrAttachment['file'];
					$arrAttachment['bytes_current'] = filesize($arrAttachment['file']);
					$intBytesTotal += $arrAttachment['bytes_current'];

					$arrAttachment['original_exists'] = (file_exists($strBackupFile));
					$arrAttachment['original_file'] = $strBackupFile;

					if(array_key_exists('tp_meta',$arrAttachment) && array_key_exists('_tp_compressed_wp_original',$arrAttachment['tp_meta'])){

						//Original attachment has been compressed
						$arrAttachment['compressed'] = true;

						$intBytesTotalCompressed += $arrAttachment['tp_meta']['_tp_compressed_wp_original']['bytes_original'];
						$intBytesSaved += ($arrAttachment['tp_meta']['_tp_compressed_wp_original']['bytes_original']-$arrAttachment['tp_meta']['_tp_compressed_wp_original']['bytes_compressed']);
					}else{
						$arrAttachment['compressed'] = false;
						$intBytesTotalOriginal += $arrAttachment['bytes_current'];
						$blAllCompressed = false;
					}

					foreach($arrAttachment['sizes'] as $strSize => $arrSizeData){
						$arrAttachment['sizes'][$strSize]['url'] = $arrUploadPaths['baseurl'].'/'.$strContentPath.'/'.$arrAttachment['sizes'][$strSize]['file'];
						$arrAttachment['sizes'][$strSize]['file'] = $arrUploadPaths['basedir'].'/'.$strContentPath.'/'.$arrAttachment['sizes'][$strSize]['file'];
						$arrAttachment['sizes'][$strSize]['bytes_current'] = (file_exists($arrAttachment['sizes'][$strSize]['file'])) ? filesize($arrAttachment['sizes'][$strSize]['file']) : 0;
						$intBytesTotal += $arrAttachment['sizes'][$strSize]['bytes_current'];

						if(array_key_exists('tp_meta',$arrAttachment) && array_key_exists('_tp_compressed_'.strtolower($strSize),$arrAttachment['tp_meta'])){

							//This size has been compressed
							$arrAttachment['sizes'][$strSize]['compressed'] = true;

							$intBytesTotalCompressed += $arrAttachment['tp_meta']['_tp_compressed_'.strtolower($strSize)]['bytes_original'];
							$intBytesSaved += ($arrAttachment['tp_meta']['_tp_compressed_'.strtolower($strSize)]['bytes_original']-$arrAttachment['tp_meta']['_tp_compressed_'.strtolower($strSize)]['bytes_compressed']);
						}else{
							$arrAttachment['sizes'][$strSize]['compressed'] = false;
							$intBytesTotalOriginal += $arrAttachment['sizes'][$strSize]['bytes_current'];
							$blAllCompressed = false;
						}
					}

					//Total bytes of data before compression
					$arrAttachment['bytes_total_original'] = ($intBytesTotalOriginal+$intBytesTotalCompressed);

					//Total bytes currently
					$arrAttachment['bytes_total'] = $intBytesTotal;

					//Total bytes of data sent to compressor
					$arrAttachment['bytes_compressed'] = $intBytesTotalCompressed;

					//Total bytes of data saved
					$arrAttachment['bytes_saved'] = $intBytesSaved;

					//Attachment and all its sizes have been compressed
					$arrAttachment['all_compressed'] = $blAllCompressed;
				}

				return $arrAttachment;
			}

			public function deleteAttachmentSize($intAttachmentID,$strSize){

				$arrAttachment = $this->getAttachment($intAttachmentID);

				if(is_array($arrAttachment) && $strSize != 'wp_original' && array_key_exists($strSize,$arrAttachment['sizes'])){

					//Remove the image file
					@unlink($arrAttachment['sizes'][$strSize]['file']);

					//Remove the size from the meta data and re-save
					$arrAttachmentMeta = wp_get_attachment_metadata($intAttachmentID,false);
					unset($arrAttachmentMeta['sizes'][$strSize]);
					wp_update_attachment_metadata($intAttachmentID,$arrAttachmentMeta);


					//Remove any TP compression records that are associated
					delete_post_meta($intAttachmentID, '_tp_compressed_'.strtolower($strSize));

					return array('status' => true, 'message' => 'Attachment supporting sized image has been remove');
				}

				return array('status' => true, 'message' => 'Failed to delete supporting sized attachment');
			}

			public function regenerateAttachmentSize($intAttachmentID,$strSize,$strOriginalFileOverride = null){

				$arrSizes = $this->getImageSizes();
				$arrAttachment = $this->getAttachment($intAttachmentID);

				if(is_array($arrAttachment) && $strSize != 'wp_original' && array_key_exists($strSize,$arrSizes)){

					$intThumbnailWidth = $arrSizes[$strSize]['width'];
					$intThumbnailHeight = $arrSizes[$strSize]['height'];

					$arrDimensions = image_resize_dimensions($arrAttachment['width'], $arrAttachment['height'], $intThumbnailWidth, $intThumbnailHeight, $arrSizes[$strSize]['crop']);

					if(!$arrDimensions){
						//No supporting size is required, delete the size if exists already
						$this->deleteAttachmentSize($intAttachmentID,$strSize);
						return array('status' => false, 'message' => 'Supporting attachment size not required for this image');
					}

					list($intDestX,$intDestY,$intSrcX,$intSrcY,$intDestWidth,$intDestHeight,$intCropWidth,$intCropHeight) = $arrDimensions;

					if(!is_null($strOriginalFileOverride)){
						$arrAttachment['file'] = $strOriginalFileOverride;
					}

					$resEditor = wp_get_image_editor($arrAttachment['file']);

					if(!is_wp_error($resEditor)){
						//$strNewFile = $resEditor->generate_filename("{$intDestWidth}x{$intDestHeight}", null, strtolower(pathinfo($arrAttachment['file'],PATHINFO_EXTENSION)));

						//Resize and save the new image
						$resEditor->resize($intDestWidth, $intDestHeight, $arrSizes[$strSize]['crop']);
						$arrSizeMeta = $resEditor->save();

						//If a backup original was used we need to copy over the regenerated create file
						if(!is_null($strOriginalFileOverride)){
							unlink($arrAttachment['sizes'][$strSize]['file']);
							copy($arrSizeMeta['path'],$arrAttachment['sizes'][$strSize]['file']);
							unlink($arrSizeMeta['path']);
						}

						//Remove path as this is not needed
						unset($arrSizeMeta['path']);

						//Add/update the new size in the metadata and re-save
						$arrAttachmentMeta = wp_get_attachment_metadata($intAttachmentID,false);
						$arrAttachmentMeta['sizes'][$strSize] = $arrSizeMeta;
						wp_update_attachment_metadata($intAttachmentID,$arrAttachmentMeta);

						return array('status' => true, 'message' => 'Successfully re-generated the supporting size attachment','size' => $strSize,'meta' => $arrSizeMeta);
					}

					return array('status' => false, 'message' => $resEditor->get_error_message());
				}

				return array('status' => false, 'message' => 'Failed to re-generate the supporting size attachment');
			}

			public function getImageSizes(){

				global $_wp_additional_image_sizes;
				$arrSizes = array(
					'wp_original' => array(
						'width' => '-',
						'height' => '-',
						'crop' => '',
						'registered' => true
					)
				);

				foreach(get_intermediate_image_sizes() as $strEachSize){

					$arrSizes[$strEachSize] = array(
						'width' => intval(get_option("{$strEachSize}_size_w")),
						'height' => intval(get_option("{$strEachSize}_size_h")),
						'crop' => get_option("{$strEachSize}_crop") ? get_option("{$strEachSize}_crop") : false,
						'registered' => true,
					);
				}

				if(isset($_wp_additional_image_sizes) && count($_wp_additional_image_sizes)){
					foreach($_wp_additional_image_sizes as $strSize => $arrEachSize){
						$arrEachSize['registered'] = true;
						$arrSizes[$strSize] = $arrEachSize;
					}
				}

				return $arrSizes;
			}
		}

		global $ToffeePress;
		$ToffeePress = new ToffeePress();
	}