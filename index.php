<?php
/**
 * Plugin Name: Vulnerabilities Check ( by Sensorete )
 * Plugin URI: http://www.sensorete.net/vulnerabilities-check
 * Description: Verifica le vulnerabilità di wordpress, dei plugins e del tema
 * Version: 0.1.1
 * Author: Sensorete
 * Author URI:  http://www.linkedin.com/in/sensorete
 * Text Domain: vulnche
 * License: GPL2
 */
define('FORCE_LOAD', false); // se true forza il download dei json da wpvulndb.com

//add menu page
function sen_vulnche_add_menu_item(){
    add_menu_page('Vulnerabilities Check','Vulnerabilities Check','activate_plugins','vulnerabilities-check','sen_vulnche_page_render');
}
add_action('admin_menu', 'sen_vulnche_add_menu_item');

//page render
function sen_vulnche_page_render(){
    echo '<div class="sen_vulnche_page_container">';

    if(isset($_GET['vulnche_do_check'])) {
        ?>
        <script>
            jQuery(document).ready(function(){jQuery( "#vulnche_tabs" ).tabs();});
        </script>

        <div id="vulnche_tabs">
            <ul>
                <li><a href="#vc_tabs_wordpress"><?php _e('Wordpress','vulnche'); ?></a></li>
                <li><a href="#vc_tabs_plugins"><?php _e('Plugins','vulnche'); ?></a></li>
                <li><a href="#vc_tabs_themes"><?php _e('Themes','vulnche'); ?></a></li>
                <li><a href="#report"><?php _e('Report to Sensorete','vulnche'); ?></a></li>
            </ul>
            <div id="vc_tabs_wordpress">
                <?php sen_vulnche_check_wp(); ?>
            </div>
            <div id="vc_tabs_plugins">
                <?php sen_vulnche_check_plungins(); ?>
            </div>
            <div id="vc_tabs_themes">
                <?php sen_vulnche_check_theme(); ?>
            </div>
	        <div id="report">
		        <h2><strong><?php _e('Send a security report','vulnche'); ?></strong></h2>
		        <p><?php _e('Send this security report to Sensorete','vulnche'); ?></p>
		        <p><?php _e('You will get a quote to secure your site','vulnche'); ?></p>
		        <br />
		        <form action="" method="get">
			        <label for="quote_mail"><?php _e('Mail address to recive a quote','vulnche'); ?>:</label><br />
			        <input type="email" name="quote_mail" id="quote_mail" /><br />
			        <input type="hidden" name="page" value="vulnerabilities-check" />
			        <input type="submit" value="<?php _e('Send','vulnche'); ?>" />
		        </form>

	        </div>
        </div>
        <?php
    }elseif(isset($_GET['quote_mail'])){

	    ob_start();

        sen_vulnche_check_wp();
        sen_vulnche_check_plungins();
        sen_vulnche_check_theme();

	    $report = ob_get_contents();
	    ob_clean();

	    $site = get_bloginfo();
	    $site_url = get_bloginfo('url');
        $style = file_get_contents(plugin_dir_path(__FILE__).'/style.css');
	    $report = '<style>'.$style.'</style>Richiesta di preventivo per messa in sicurezza del sito '.$site_url.'<br />'.
	        'Inviare preventivo a: '.$_GET['quote_mail'].'<br /><br />'.
	        $report;

	    add_filter( 'wp_mail_content_type', 'sen_vulnche_set_content_type' );
            function sen_vulnche_set_content_type( $content_type ) {
		    return 'text/html';
	    }
	    $mail_send = wp_mail('assistenza@sensorete.net', 'Richiesta preventivo da '.$site, $report);
	    remove_filter( 'wp_mail_content_type', 'sen_vulnche_set_content_type' );

        $img_src = plugin_dir_url(__FILE__).'/imgs/';

	    if($mail_send){
            echo '<img class="vulnche_status_img" src="'.$img_src.'ok.png" />';
            echo '<h2>'.__('Request sent','vulnche').'</h2>';
		    echo '<p>'.__('Your request has been sent successfully','vulnche').'</p>';
		    echo '<p>'.__('You will be contacted as soon as possible','vulnche').'</p>';
	    }else{
            echo '<img class="vulnche_status_img" src="'.$img_src.'ko.png" />';
            echo '<h2>'.__('Request NOT sent','vulnche').'</h2>';
		    echo '<p>'.__('It was a problem with sending the email','vulnche').'</p>';
		    echo '<p>'.__('Contact directly Sensorete to:','vulnche').' <a href="mailto:assistenza@sensorete.net">assistenza@sensorete.net</a></p>';
	    }
    }else{
        echo '<h2>'.__('known vulnerabilities check','vulnche').':</h2>';
        echo '<p>'.__('Do known vulnerabilities check','vulnche').'</p>';
        echo '<form action="" method="get">
            <input type="submit" name="check" value="'.__('Check for vulnerabilities','vulnche').'">
            <input type="hidden" value="vulnerabilities-check" name="page">
            <input type="hidden" name="vulnche_do_check" value="">
        </form>';
    }
    echo '</div>';
}

//script e style enqueue
function sen_vulnche_enqueue_scr(){
	global $pagenow;

	if('admin.php' == $pagenow && 'vulnerabilities-check' == $_GET['page']) {
		wp_enqueue_script( 'jquery-ui-tabs' );
		wp_enqueue_style( 'jquery-ui-style', plugin_dir_url(__FILE__).'/css/jquery-ui.min.css' );
		wp_enqueue_style( 'sen-vuncheck-style', plugin_dir_url(__FILE__).'/css/style.css' );
	}
}
add_action('admin_enqueue_scripts','sen_vulnche_enqueue_scr');

//check functions
function sen_vulnche_check_plungins(){
    $active_plugins = get_option('active_plugins');
    if(!empty($active_plugins)){
        echo '<h2><strong>'.__('Plugins','vulnche').'</strong></h2><br />';
        foreach($active_plugins as $plug){
            $string = explode('/',$plug);
            $plug_vuln = get_transient('sen_vulnche_trans_plug_'.$string[0]);
            if(false === $plug_vuln || FORCE_LOAD) {
                $plug_vuln = sen_vulnche_get_content('https://wpvulndb.com/api/v2/plugins/' . $string[0]);
                set_transient('sen_vulnche_trans_plug_'.$string[0],$plug_vuln, 1 * DAY_IN_SECONDS);
            }
            if(!empty($plug_vuln)){
                $plug_vuln = json_decode($plug_vuln, true);
                foreach($plug_vuln as $pl_name => $vuln_data){
                    echo '<div class="vulnche_obj_container">';
                    echo '<div class="vulnche_obj_title">';
                    $img_src = plugin_dir_url(__FILE__).'/imgs/';
                    $img_src .= empty($vuln_data['vulnerabilities']) ? 'ok.png' : 'ko.png';
                    echo '<img class="vulnche_status_img" src="'.$img_src.'" />';
                    echo '<strong>'.__('Name','vulnche').': '.$pl_name.'</strong><br />'.__('Latest version','vulnche').': '.$vuln_data['latest_version'].' | '.__('Latest update','vulnche').': '.$vuln_data['last_updated'].'<br /><br />';
                    echo '</div>';
                    if(!empty($vuln_data['vulnerabilities'])) {
                        sen_vulnche_write_list($vuln_data['vulnerabilities']);
                    }
                    echo '</div>';
                }
            }else{
                echo '<div class="vulnche_obj_container">';
                echo '<div class="vulnche_obj_title">';
                $img_src = plugin_dir_url(__FILE__).'/imgs/qm.png';
                echo '<img class="vulnche_status_img" src="'.$img_src.'" />';
                echo 'Plugin not in database: '.$plug.'<br /><br />';
                echo '</div>';
                echo '</div>';
            }
        }
    }
}
function sen_vulnche_check_wp(){
    $wp_vuln = get_transient('sen_vulnche_trans_wp_json');
    if(false === $wp_vuln || FORCE_LOAD) {
        $wp_version = str_replace('.', '', get_bloginfo('version'));
        $wp_vuln = sen_vulnche_get_content('https://wpvulndb.com/api/v2/wordpresses/' . $wp_version);
        set_transient('sen_vulnche_trans_wp_json', $wp_vuln, 1 * DAY_IN_SECONDS);
    }
    if(!empty($wp_vuln)){
        $wp_vuln = json_decode($wp_vuln, true);
        foreach($wp_vuln as $wp_v => $vuln_data){
            echo '<div class="vulnche_obj_container">';
            echo '<div class="vulnche_obj_title">';
            $img_src = plugin_dir_url(__FILE__).'/imgs/';
            $img_src .= empty($vuln_data['vulnerabilities']) ? 'ok.png' : 'ko.png';
            echo '<img class="vulnche_status_img" src="'.$img_src.'" />';
            echo '<h2><strong>'.__('Wordpress','vulnche').'</strong></h2><br />';
            echo 'Versione wordpress: '.$wp_v.'<br /><br />';
            echo '</div>';
            if(!empty($vuln_data['vulnerabilities'])) {
                sen_vulnche_write_list($vuln_data['vulnerabilities']);
            }
            echo '</div>';
        }
    }
}
function sen_vulnche_check_theme(){
    if ( current_user_can( 'switch_themes' ) ) {
        $themes = wp_prepare_themes_for_js();
    } else {
        $themes = wp_prepare_themes_for_js( array( wp_get_theme() ) );
    }
    wp_reset_vars( array( 'theme', 'search' ) );
    if(!empty($themes)){
        echo '<h2><strong>'.__('Themes','vulnche').'</strong></h2>';
        foreach($themes as $th){
            if(empty($th['parent'])) {
                $th_vuln = get_transient('sen_vulnche_trans_theme_' . $th['id']);
                if (false === $th_vuln || FORCE_LOAD) {
                    $th_vuln = sen_vulnche_get_content('https://wpvulndb.com/api/v2/themes/' . $th['id']);
                    set_transient('sen_vulnche_trans_theme_' . $th['id'], $th_vuln, 1 * DAY_IN_SECONDS);
                }
                $th_vuln = json_decode($th_vuln, true);

                foreach($th_vuln as $wp_v => $vuln_data){
                    echo '<div class="vulnche_obj_container">';
                    echo '<div class="vulnche_obj_title">';
                    $img_src = plugin_dir_url(__FILE__).'/imgs/';
                    if(empty($vuln_data['vulnerabilities']) && !empty($vuln_data['latest_version'])){
                        $img_src .= 'ok.png';
                    }elseif(empty($vuln_data['vulnerabilities']) && empty($vuln_data['latest_version'])){
                        $img_src .= 'qm.png';
                    }else{
                        $img_src .= 'ko.png';
                    }

                    echo '<img class="vulnche_status_img" src="'.$img_src.'" />';
                    echo '<strong>'.__('Name','vulnche').': '.$th['name'].' by '.$th['author'].'</strong><br />'.__('Latest version','vulnche').': '.$vuln_data['latest_version'].' | '.__('Latest update','vulnche').': '.$vuln_data['last_updated'].'<br /><br />';
                    echo '</div>';
                    if(!empty($vuln_data['vulnerabilities'])) {
                        sen_vulnche_write_list($vuln_data['vulnerabilities']);
                    }
                }
                echo '</div>';
            }
        }
    }
}

function sen_vulnche_write_list($vulnerabilities){
    echo '<ul>';
    foreach ( $vulnerabilities as $vulnerability ) {
        echo '<li>';
        echo $vulnerability['title'] . '<br />type: ' . $vulnerability['vuln_type'] . '<br />fixed in version: ' . $vulnerability['fixed_in'] . '<br />';
        echo 'References:<br />';
        if ( isset( $vulnerability['references']['url'] ) ) {
            foreach ( $vulnerability['references']['url'] as $ref ) {
                echo '<a target="_blank" href="' . $ref . '">' . $ref . '</a><br />';
            }
        } else {
            echo 'None';
        }
        echo '</li>';
    }
    echo '</ul>';
}
//curl utils
function sen_vulnche_get_content ($url) {

    // Crea la risorsa CURL
    $ch = curl_init();

    // Imposta l'URL e altre opzioni
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_USERAGENT, sen_vulnche_get_random_user_agent());
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
    // Scarica l'URL e lo passa al browser
    $output = curl_exec($ch);
    $info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // Chiude la risorsa curl
    curl_close($ch);
    if ($output === false || $info != 200) {
        $output = null;
    }
    return $output;

}
function sen_vulnche_get_random_user_agent ( ) {
    $someUA = array (
        "Mozilla/5.0 (Windows; U; Windows NT 6.0; fr; rv:1.9.1b1) Gecko/20081007 Firefox/3.1b1",
        "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.0",
        "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/525.19 (KHTML, like Gecko) Chrome/0.4.154.18 Safari/525.19",
        "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/525.13 (KHTML, like Gecko) Chrome/0.2.149.27 Safari/525.13",
        "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0; .NET CLR 1.1.4322; .NET CLR 2.0.50727; .NET CLR 3.0.04506.30)",
        "Mozilla/4.0 (compatible; MSIE 7.0b; Windows NT 5.1; .NET CLR 1.1.4322; .NET CLR 2.0.40607)",
        "Mozilla/4.0 (compatible; MSIE 7.0b; Windows NT 5.1; .NET CLR 1.1.4322)",
        "Mozilla/4.0 (compatible; MSIE 7.0b; Windows NT 5.1; .NET CLR 1.0.3705; Media Center PC 3.1; Alexa Toolbar; .NET CLR 1.1.4322; .NET CLR 2.0.50727)",
        "Mozilla/45.0 (compatible; MSIE 6.0; Windows NT 5.1)",
        "Mozilla/4.08 (compatible; MSIE 6.0; Windows NT 5.1)",
        "Mozilla/4.01 (compatible; MSIE 6.0; Windows NT 5.1)");
    srand((double)microtime()*1000000);
    return $someUA[rand(0,count($someUA)-1)];
}