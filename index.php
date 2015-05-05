<?php
/*
Plugin Name: SPIP Media Importer
Plugin URI: http://lucilio.net/spip-mysql-importer
Description: Import media content from a SPIP istalation connecting direct to MYSQL database and reading imported post tags
Author: lucilio.net
Author URI: http://lucilio.net
Version: 0.0.1
Text Domain: spip-media-importer
License: WTFPL
*/

/** Display verbose errors */
define( 'IMPORT_DEBUG', true );

/**
* 
*/
class SpipMediaImporter
{
	
	function __construct(){
		add_action( 'admin_init', array( $this, 'plugin_options' ) );
	}

	function __destruct(){
		;
	}

	function __get( $property ){
		if( $property == 'options' ){
			$options = get_option( 'spip_import', array() );
			return $options;
		}
		elseif( $property == 'imported_setup' ){
			$options = $this->options;
			if( array_key_exists( 'imported_setup', $options ) ){
				return $options['imported_setup'];
			}
			elseif( array_key_exists( 'connection', $options ) ){
				$imported_setup = array();
				$results = mysql_query( "SELECT * FROM spip_meta;" );
				if( $results ){
					while( $row = mysql_fetch_assoc( $results ) ){
						$imported_setup[ $row['nom'] ] = $row['valeur'];
					}
					return $imported_setup;
				}
			}
			return FALSE;
		}
		elseif( $property == 'connection' ){
			$options = $this->options;
			if( function_exists('mysql_connect') ){
				$link = mysql_connect( $options['connection']['host'], $options['connection']['username'], $options['connection']['password'] );
				if( !( $connection_error = mysql_errno() ) ){
					mysql_select_db( $options['connection']['database'], $link );
					if( !( $connection_error = mysql_errno() ) ){
						return $link;
					}
					else{
						add_settings_error( 'spip_import', 'connection-host', __("Can't connect to database: ") . mysql_error(), 'error' );
					}
				}
				else{
					//*DEBUG*/		die( __('Connection failure') . var_export( compact( 'options' ) ) . mysql_error() );
					add_settings_error( 'spip_import', 'connection-host', __("Can't connect to mysql: ") . mysql_error(), 'error' );
				}
			}
			else{
				add_settings_error( 'spip_import', 'connection-host', __("MySQL library not found."), 'error' );
			}
			return FALSE;
		}
		else{
			return NULL;
		}
	}

	function plugin_options(){
		add_settings_section(
			'spip_import_connection_section',
			__('SPIP Media Importer Connection Credentials'),
			array( $this, 'spip_import_section_callback'),
			'media'
			)
		;
		register_setting(
			'media',
			'spip_import',
			array( $this, 'spip_import_options_check' )
			)
		;
		if( !$this->connection ){
			add_settings_field(
				'connection_host',
				__('Host'),
				array( $this, 'spip_import_connection_host' ),
				'media',
				'spip_import_connection_section'
				)
			;
			add_settings_field(
				'connection_database',
				__('Database Name'),
				array( $this, 'spip_import_connection_database' ),
				'media',
				'spip_import_connection_section'
				)
			;
			add_settings_field(
				'connection_user',
				__('Username'),
				array( $this, 'spip_import_connection_username' ),
				'media',
				'spip_import_connection_section'
				)
			;
			add_settings_field(
				'connection_password',
				__('Password'),
				array( $this, 'spip_import_connection_password' ),
				'media',
				'spip_import_connection_section'
				)
			;
		}
		else{
			add_settings_field(
				'connection_reset',
				__('Erase connection data'),
				array( $this, 'spip_import_connection_reset' ),
				'media',
				'spip_import_connection_section'
				)
			;
			add_settings_field(
				'do_importing',
				__('Import Content'),
				array( $this, 'spip_import_do_importing' ),
				'media',
				'spip_import_connection_section'
				)
			;
			add_settings_field(
				'import_media',
				__('Import Media'),
				array( $this, 'spip_import_media' ),
				'media',
				'spip_import_connection_section'
				)
			;
			add_settings_field(
				'database_users',
				__('Import this users'),
				array( $this, 'spip_import_users' ),
				'media',
				'spip_import_connection_section'
				)
			;
			add_settings_field(
				'database_tags',
				__('Import this tags'),
				array( $this, 'spip_import_tags' ),
				'media',
				'spip_import_connection_section'
				)
			;
			add_settings_field(
				'database_categories',
				__('Import this categories'),
				array( $this, 'spip_import_categories' ),
				'media',
				'spip_import_connection_section'
				)
			;
			add_settings_field(
				'database_posts',
				__('Import this Posts'),
				array( $this, 'spip_import_posts' ),
				'media',
				'spip_import_connection_section'
				)
			;
			add_settings_field(
				'database_links',
				__('Import this Links'),
				array( $this, 'spip_import_links' ),
				'media',
				'spip_import_connection_section'
				)
			;
/*			add_settings_field(
				'database_content',
				__('Database content'),
				array( $this, 'spip_import_database_content' ),
				'media',
				'spip_import_connection_section'
				)
			;
*/		}
	}

	function spip_import_do_importing(){
		?>
		<label>
			<input type="checkbox" id="spip_import_do_importing" name="spip_import[do_importing][import__all]">
			<strong><?php echo __('Import Everything'); ?></strong>
		</label>
		<?php
	}

	function spip_import_users(){
		$options = $this->options;
		$results = $this->query( "SELECT BINARY login AS user_login, BINARY nom AS display_name, BINARY email AS user_email FROM spip_auteurs;" );
		?>
		<label>
			<input type="checkbox" id="spip_import_user_import__all" name="spip_import[do_importing][user][import__all]">
			<strong><?php echo __('Import all users'); ?></strong>
		</label>
		<dl>
		<?php
		while( $results and $row = mysql_fetch_assoc( $results ) ){
			$flags = $errors = array();
			$classes = array('importing');
			$user = NULL;
			extract( $row );
			if( $this->do_importing('user', $user_login ) ){
				$flags[] = 'checked';
			}
			if( username_exists( $user_login ) ){
				$flags[] = 'disabled';
				$user_login = $user_login;
			}
			if( in_array( 'checked', $flags ) and !in_array( 'disabled', $flags ) ){
				$user = wp_insert_user( $row );
				if( is_wp_error( $user ) ){
					$errors = $user->get_error_messages();
					$classes = $flags;
					$classes[] = 'error';
				}
			}
			?>
			<dt>
				<label for="spip_import_<?php echo $user_login; ?>" class="<?php echo implode(' ', $classes ); ?>">
					<input id="spip_import_user_<?php echo $user_login; ?>" type="checkbox" name="spip_import[do_importing][user][<?php echo $user_login; ?>]" title="Import <?php echo $user_login; ?>"<?php echo implode(' ', $flags); ?>>
					<?php echo ( empty( $display_name ) ? $user_login : $display_name ); ?>
				</label>
			</dt>
				<?php
				foreach( $errors as $error_message ){
					?>
				<dd class="error">
					<?php echo $error_message; ?>
				</dd>	
					<?php
				}
				?>
			<dd><strong>username: </strong><?php echo $user_login . ( in_array( 'disabled', $flags ) ? (' <em>[' . __('User already exists on database.') . ']</em>') : '' ); ?></dd>
			<dd><strong>email: </strong><?php echo $user_email; ?></dd>
			<?php
		}
		?>
		</dl>
		<?php
	}

	function spip_import_tags(){
		$results = $this->query( "SELECT id_mot AS spip_id, BINARY titre AS tag, CONCAT( BINARY descriptif, IF(  ( descriptif != '' AND texte != ''), '\n', '' ), BINARY texte ) AS description  FROM spip_mots;" );
		?>
		<div>
			<label for="spip_import_tag_import__all">
				<input type="checkbox" id="spip_import_tag_import__all" name="spip_import[do_importing][tag][import__all]">
				<strong><?php echo __('Import all tags'); ?></strong>
			</label>
		</div>
		<div>
			<?php
		while( $results and $row = mysql_fetch_assoc( $results ) ){
			extract( $row );
			$flags = $errors = array();
			$classes = array('importing');
			$slug = sanitize_key( $tag );
			if( $this->do_importing('tag', $slug ) ){
				$flags[] = 'checked';
			}
			if( term_exists( $tag, 'post_tag' ) ){
				$flags[] = 'disabled';
			}
			if( in_array( 'checked', $flags ) and !in_array( 'disabled', $flags ) ){
				$wp_tag = wp_insert_term( $tag, 'post_tag', array( 'description'	=>	$description ) );
				if( is_wp_error( $wp_tag ) ){
					$errors = $wp_tag->get_error_messages();
					$classes[] = 'error';
				}

			}
			?>
			<label for="spip_import_tag_<?php echo $slug; ?>" title="[<?php echo $slug ?>] <?php echo $description; ?>">
				<input id="spip_import_tag_<?php echo $slug; ?>" type="checkbox" name="spip_import[do_importing][tag][<?php echo $slug; ?>]" title="Import <?php echo $tag; ?>"<?php echo ( count($flags)?' ':'' ) . implode(' ', $flags); ?>>
				<?php echo $tag; ?>,&nbsp;
			</label>
			<?php
			foreach( $errors as $error_message ){
				?>
			<span class="error">
				[<?php echo $error_message; ?>]&nbsp;
			</span>	
				<?php
			}
			?>
			<?php
		}
			?>
		</div>
		<?php
	}

	function spip_import_categories(){
		$results = $this->query( "SELECT id_parent AS id_parent_category , BINARY titre AS category,CONCAT( BINARY descriptif, IF(  ( descriptif != '' AND texte != ''), '\n', '' ), BINARY texte ) AS description, (SELECT BINARY titre FROM spip_rubriques WHERE id_rubrique = id_parent_category ) AS parent_category FROM spip_rubriques ORDER BY category;" );
		?>
		<ul>
			<li>
				<label for="spip_import_category_import__all">
					<input id="spip_import_category_import__all" type="checkbox" name="spip_import[do_importing][category][import__all]" title="<?php echo __("Import All"); ?>">
					<?php echo __("Import All"); ?>
				</label>
			</li>
			<?php
		while( $results and $row = mysql_fetch_assoc( $results ) ){
			extract( $row );
			$flags = $errors = array();
			$classes = array('importing');
			$slug = sanitize_key( $category );
			if( $this->do_importing('category', $slug ) ){
				$flags[] = 'checked';
			}
			if( term_exists( $category, 'category' ) ){
				$flags[] = 'disabled';
			}
			if( in_array( 'checked', $flags ) and !in_array( 'disabled', $flags ) ){
				$wp_category = wp_insert_term(
					$category,
					'category',
					array(
						'slug'			=>	sanitize_key( $category ),
						'description'	=>	$description
						)
					)
				;
				if( is_wp_error( $wp_category ) ){
					$errors = $user->get_error_messages();
					$classes[] = 'error';
				}
			}
			?>
			<li>
				<label for="spip_import_tag_<?php echo $slug; ?>" title="[<?php echo $slug ?>] <?php echo $description; ?>">
					<input id="spip_import_tag_<?php echo $slug; ?>" type="checkbox" name="spip_import[do_importing][tag][<?php echo $slug; ?>]" title="Import <?php echo $tag; ?>"<?php echo ( count($flags)?' ':'' ) . implode(' ', $flags); ?>>
					<strong><?php echo $category; ?></strong><?php if( !empty( $description ) ){ ?><em><?php echo $description; ?></em><?php } ?>
					<?php if( !empty( $id_parent_category ) ){ ?>[child of <em><?php echo $parent_category; ?></em>]<?php } ?>
				</label>
			</li>
			<?php
			foreach( $errors as $error_message ){
				?>
			<span class="error">
				[<?php echo $error_message; ?>]&nbsp;
			</span>	
				<?php
			}
		}
		?>
		</ul>
		<?php
	}

	function spip_import_posts(){
		$results = $this->query( "SELECT id_article AS spip_id, BINARY titre AS post_title, CONCAT(BINARY surtitre, \"\n\",BINARY descriptif,\"\n\",BINARY soustitre) AS post_excerpt, CONCAT(BINARY chapo, \"\n\",BINARY texte,\"\n\",BINARY ps) AS post_content, statut AS post_status, date AS post_date, id_rubrique AS category FROM spip_articles;" );
		?>
		<ul>
			<li>
				<label for="spip_import_post_import__all">
					<input type="checkbox" id="spip_import_post_import__all" name="spip_import[do_importing][post][import__all]">
					<?php echo __("Import All"); ?>
				</label>
			</li>
			<?php
			while( $results and $row = mysql_fetch_assoc( $results ) ){
				extract( $row );
				$wp_staus = array( 'publie' => 'publish', 'prepa' => 'draft', 'prop' => 'pending' );
				$post_status = $wp_staus[ $post_status ];
				$post_author = implode( ',' , $this->get_authors( $spip_id ) );
				$slug = sanitize_key( $post_title );
				$flags = $errors = array();
				$classes = array('importing');
				$category_chain = $this->get_category_chain( $category );
				$tags_input = implode( ', ', $this->get_keywords( $spip_id ) );
				if( $this->do_importing('post', $slug ) ){
					$flags[] = 'checked';
				}
				if( get_page_by_title( $post_title, ARRAY_A, 'post' ) ){
					$flags[] = 'disabled';
				}
				if( in_array( 'checked', $flags ) and !in_array( 'disabled', $flags ) ){
					$post_type = 'post';
					$post_name = $slug;
					$wp_category_id = get_cat_ID( $category_chain[0] );
					$wp_category = get_category_by_slug( sanitize_key( $category_chain[0] ) );
					$post_category = array( $wp_category_id );
/*DEBUG*/ echo var_export( compact('post_category') );
					$insert_post_data = compact(
						'post_title',
						'post_name',
						'post_excerpt',
						'post_content',
						'post_status',
						'post_date',
						'post_author',
						'post_category',
						'tags_input'
					);
					$wp_id = wp_insert_post( $insert_post_data );
					if( is_wp_error( $wp_id ) ){
						$errors = $user->get_error_messages();
						$classes[] = 'error';
					}
				}
				else{
					$post_content = $this->parse_content( $post_content );
					$post_excerpt = $this->parse_content( $post_excerpt );
				}
				?>
				<li>
					<label for="spip_import_post_<?php echo $slug ?>" style="vertical-align:middle;">
						<input type="checkbox" id="spip_import_post_<?php echo $slug ?>" name="spip_import[do_importing][post][<?php echo $slug ?>]"title="Import <?php echo $post_title; ?>"<?php echo ( count($flags)?' ':'' ) . implode(' ', $flags); ?> style="float:left">
						<h1 class="header"><?php echo $post_title; ?></h1>
					</label>
					<?php if( isset( $post_excerpt ) ){
						?>
					<p class="content"><em><?php echo $post_excerpt; ?></em></p>
						<?php
					}
					?>
					<p class="content"><?php echo $post_content; ?></p>
					<p class="footer">
						<em><?php echo $post_date; ?></em> 
						<em><?php echo __( $post_status ); ?></em> 
						<em><strong><?php echo $post_author; ?></strong></em>
						[<?php echo $spip_id ?>]
					</p>
					<p>categoria: <em><?php echo 	implode( '</em> <strong>-&gt;</strong> <em>' , $category_chain ) ?></em></p>
					<p>tags: <em><?php echo $tags_input; ?></em></p>
					<hr>
				</li>
				<?php
			}
			?>
		</ul>
		<?php
	}

	function spip_import_links(){
		$spip_link_category = get_category_by_slug('spip_imported_link');
		if( !$spip_link_category ){
			$spip_link_category = wp_insert_term( 'SPIP Imported Link', 'category', array('slug'=>'spip_imported_link') );
		}
		?>
		<ul>
			<li>
				<label for="spip_import_link_import__all">
					<input type="checkbox" id="spip_import_link_import__all" name="spip_import[do_importing][link][import__all]">
					<?php echo __("Import All") ?>
				</label>
			</li>
		</ul>
		<?php
		$results = $this->query(
			"SELECT id_breve AS spip_id, BINARY lien_titre AS caption, CONCAT( BINARY titre,\"\n\", BINARY texte) AS hint, lien_url AS src, statut AS post_status, date_heure AS post_date, id_rubrique AS category FROM spip_breves;"
			);
		while( $results and $row = mysql_fetch_assoc( $results ) ){
			extract( $row );
			$category_chain = $this->get_category_chain( $category );
			$wp_category = get_category_by_slug( sanitize_key( $category_chain[0] ) );
			$post_category = array( $wp_category->term_id, $spip_link_category->term_id );
			$slug = sanitize_key( $caption . $spip_id );
			$src = $this->parse_link( $src );
			$flags = array();
			if( $this->do_importing('link', $slug ) ){
				$flags[] = 'checked';
			}
			if( get_page_by_title( $post_title, ARRAY_A, 'nav_menu_item' ) ){
				$flags[] = 'disabled';
			}
			if( in_array( 'checked', $flags ) and !in_array( 'disabled', $flags ) ){
				$menu_name = 'SPIP Import Menu';
				if( $spip_import_menu = wp_get_nav_menu_object( $menu_name ) ){
					$menu_id = $spip_import_menu->term_id;
				}
				else{
					$menu_id = wp_create_nav_menu( $menu_name );
				}
				wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-title'	=>	$caption,
						'menu-item-url'	=>	$src,
						'menu-item-attr-title' => $hint,
						'menu-item-status' => 'publish'
						)
					)
				;
			}
			?>
			<li>
				<label for="spip_import_link_<?php echo 'breve_' . sanitize_key( $caption . $spip_id ) ?>">
					<input type="checkbox" id="spip_import_link_<?php echo 'breve_' .  sanitize_key( $spip_id ) ?>" name="spip_import[do_importing][link][<?php echo 'breve_' .  sanitize_key( $spip_id ) ?>]" title="Import <?php echo $caption; ?>"<?php echo ( count($flags)?' ':'' ) . implode(' ', $flags); ?> style="float:left">
					<?php echo $caption ?> -- <em><?php echo substr( $hint , 0, 65) ?></em> <?php echo "[$src]"; ?>
				</label>
			</li>
			<?php
		}
	}

	function spip_import_media(){
		if( $this->do_importing('media') ){
			$mediaset = $this->parse_media();
			die(var_export( compact('mediaset')));
		}
	?>
		<label>
			<input type="checkbox" id="spip_import_media_import__all" name="spip_import[do_importing][media][import__all]" checked>
			<?php echo __('Import all media too'); ?>
		</label>
	<?php
	}

	function spip_sideload_media( $remote_url ){
		return $local_url;
	}


	function spip_import_section_callback(){
		?>
		<style type="text/css">
		.footer{
			background-color: #DDD;
		}
		label.error input{
			border: 1px #F00 solid;
		}
		dd.error{
			color: #F00;
		}
		label.disabled{
			text-decoration: line-through;
		}
		</style>
		<script type="text/javascript">
		var spipImportLoad = function(){
			var checkboxes = jQuery('input[type=checkbox][name^=spip_import\\[do_importing\\]]');
			checkboxes.on('change', spipImportSyncCheckboxes);
		}
		var spipImportSyncCheckboxes = function(event){
			var matches = event.target.name.match(/spip_import\[do_importing\]\[(\w+)\](?:\[(\w+)\])?/);
			var checkboxClass = matches[1];
			var checkboxID = matches[2];
			if(checkboxID=='import__all'){
				var classFieldSet = jQuery('input[type=checkbox][name^=spip_import\\[do_importing\\]\\['+checkboxClass+'\\]]').not(event.target);
				if(classFieldSet.length){
					classFieldSet.prop('checked',event.target.checked);
				}
			}
			if(!checkboxID&&checkboxClass=='import__all'){
				var classFieldSet = jQuery('input[type=checkbox][name^=spip_import\\[do_importing\\]]').not('[name*=spip_import\\[import__all\\]]');
				if(classFieldSet.length){
					classFieldSet.prop('checked',event.target.checked);
				}
			}
			spipImportSyncClass(checkboxClass);
			spipImportSyncAll();
		}
		var spipImportSyncClass = function(className){
			var importAll = jQuery('input[type=checkbox][name=spip_import\\[do_importing\\]\\['+className+'\\]\\[import__all\\]]');
			var importAllSet = jQuery('input[type=checkbox][name^=spip_import\\[do_importing\\]\\['+className+'\\]]').not(importAll);
			importAll.prop('checked',importAllSet.map(function(index,element){return element.checked}).toArray().reduce(function(prev,curr){return prev&&curr}));
		}
		var spipImportSyncAll = function(){
			var importAll = jQuery('input[type=checkbox][name=spip_import\\[do_importing\\]\\[import__all\\]]');
			var importAllSet = jQuery('input[type=checkbox][name$=\\[import__all\\]]').not(importAll);
			importAll.prop('checked',importAllSet.map(function(index,element){return element.checked}).toArray().reduce(function(prev,curr){return prev&&curr}));
		}
		jQuery(document).ready(spipImportLoad);
		</script>
		<?php
		submit_button();
	}

	function spip_import_options_check( $input ){
		$current_options = $this->options;
		$spip_import_options =  ! empty( $input['connection']['reset'] ) ? array( 'connection' => NULL ) : array(
			'connection'	=>	array(
				'host'		=>	empty( $input['connection']['host'] )		?	( empty( $current_options['connection']['host'] )		?	NULL	:	$current_options['connection']['host'] )	:	$input['connection']['host'],
				'username'	=>	empty( $input['connection']['username'] )	?	( empty( $current_options['connection']['username'] )	?	NULL	:	$current_options['connection']['username'] )	:	$input['connection']['username'],
				'password'	=>	empty( $input['connection']['password'] )	?	( empty( $current_options['connection']['password'] )	?	NULL	:	$current_options['connection']['password'] )	:	$input['connection']['password'],
				'database'	=>	empty( $input['connection']['database'] )	?	( empty( $current_options['connection']['database'] )	?	NULL	:	$current_options['connection']['database'] )	:	$input['connection']['database'],
				'reset' => FALSE,
				)
			)
		;
		if( isset( $input['connection']['host'] ) and empty( $input['connection']['host'] ) ){
			add_settings_error( 'spip_import', 'connection-host', __('Please set the server host'), 'error' );
		}
		if( isset( $input['connection']['username'] ) and empty( $input['connection']['username'] ) ){
			add_settings_error( 'spip_import', 'connection-username', __('You must set a username'), 'error' );
		}
		if( isset( $input['connection']['password'] ) and empty( $input['connection']['password'] ) ){
			add_settings_error( 'spip_import', 'connection-password', __('Password can not be empty'), 'error' );
		}
		if( isset( $input['connection']['database'] ) and empty( $input['connection']['database'] ) ){
			add_settings_error( 'spip_import', 'connection-database', __('Please provide the name of the database to connect'), 'error' );
		}
		if( isset( $input['do_importing'] ) ){
			$spip_import_options['do_importing'] = $input['do_importing'];
		}
		return $spip_import_options;
	}

	function spip_import_connection_host(){
		$host = ( isset( $this->options['connection'] ) and isset( $this->options['connection']['host'] ) ) ? $this->options['connection']['host'] : NULL;
		?>
		<input type="text" id="spip_import_connection_host" name="spip_import[connection][host]"<?php if( isset( $host ) ) echo " value=\"$host\""; ?>>
		<?php
	}

	function spip_import_connection_database(){
		$database = ( isset( $this->options['connection'] ) and isset( $this->options['connection']['database'] ) ) ? $this->options['connection']['database'] : NULL;
		?>
		<input type="text" id="spip_import_connection_database" name="spip_import[connection][database]"<?php echo ( $database ? " value=\"$database\"" : "" ); ?>>
		<?php
	}

	function spip_import_connection_username(){
		$username = ( isset( $this->options['connection'] ) and isset( $this->options['connection']['username'] ) ) ? $this->options['connection']['username'] : NULL;
		?>
		<input type="text" id="spip_import_connection_username" name="spip_import[connection][username]"<?php echo ( $username ? " value=\"$username\"" : "" ); ?>>
		<?php
	}

	function spip_import_connection_password(){
		$password = ( isset( $this->options['connection'] ) and isset( $this->options['connection']['password'] ) ) ? $this->options['connection']['password'] : NULL;
		?>
		<input type="password" id="spip_import_connection_password" name="spip_import[connection][password]"<?php echo ( $password ? " value=\"$password\"" : "" ); ?>>
		<?php
	}

	function spip_import_connection_reset(){
		$reset = ( isset( $this->options['connection'] ) and isset( $this->options['connection']['reset'] ) and $this->options['connection']['reset'] );
		?>
		<label>
			<input type="checkbox" id="spip_import_connection_reset" name="spip_import[connection][reset]"<?php echo ( $reset ? " checked" : "" ); ?>>
			<code style="color: red;"><?php echo"{$this->options[connection][username]}@{$this->options[connection][host]}"; ?></code>			
		</label>
		<hr>
		<?php
	}

	function spip_import_database_content(){
/*debug*/
		$tables = mysql_query( "SHOW TABLES", $this->connection );
		?>
		<ul>
		<?php
		while( $tables_query = mysql_fetch_row( $tables ) ){
			$tablename = $tables_query[0];
			?>
			<li>
				<h1><?php echo $tablename  ?></h1>
				<?php
				$fields = mysql_query( "SHOW COLUMNS FROM $tablename" );
				while( $fields_query = mysql_fetch_assoc( $fields ) ){
					 $fieldname = $fields_query['Field'] . ' ' . $fields_query['Type'];
					?>
					<em><?php echo "$fieldname, " ?></em>
					<?php
				}
				?>
			</li>
			<?php
		}
		?>
		</ul>
		<?php
/*debug*/
	}

	function query( $SQL ){
		if( $connection = $this->connection ){
			$results = mysql_query( $SQL, $connection );
			if( !$results ){
				echo mysql_error();
			}
			return $results;
		}
		return FALSE;
	}

	function parse_content( $content ){
		$content = preg_replace( '/\{{3}(?P<content>.*?)\}{3}/m', "<h3>$1</h3>", $content );//subtitles
		$content = preg_replace( '/\{{2}(?P<content>.*?)\}{2}/m', "<strong>$1</strong>", $content );
		$content = preg_replace( '/\{+(?P<content>[^\}]*?)\}+/m', "<em>$1</em>", $content );
		$content = preg_replace_callback( '/<(?<tag>img|doc|emb)(?<id>\d+)(?:\|(?<filters>[^>]*))?>/', array( $this, 'parse_tags' ), $content );
		$content = preg_replace_callback( '/(?:\R\|\s+.*\|)+/m', array( $this, 'parse_tables' ), $content );
		$content = preg_replace( '/\[(?P<caption>.*)\-\>(?P<url>.*)\]/', '<a href="$2">$1</a>', $content );
		$content = preg_replace( '/(?:\A|\R)(.*)(?:\R{2,}|\Z)/', "<p>$1</p>", $content );
		return $content;
	}

	function parse_tags( $args ){
		if( !isset( $args['id'] ) ){
			return __('No media ID found');
		}
		$media = $this->parse_media( $args['id'] );
		if( strtolower( $args['tag'] ) == 'img' ){
			$size = "";
			if( !empty( $media['width'] ) ){
				$size .= " WIDTH {$media[width]}";
			}
			if( !empty( $media['height'] ) ){
				$size .= " WIDTH {$media[height]}";
			}
			return '<img src="' . $media['file'] . '"' . $size . $this->parse_filter( $args['filters'] ) . '/>';
		}
		else{
			return "{$args[tag]}|{$args[id]}|{$args[filters]}|";
		}
	}

	function parse_tables( $args ){
		$table_rows = preg_split( '/\|\s*\|/', $args[0] );
		ob_start();
		?>
		<table>
		<?php
		foreach( $table_rows as $table_row ){
			?>
			<tr>
				<?php
				$table_cells = explode( '|', trim( $table_row, " \t\n\r\0\x0B\|" ) );
				foreach( $table_cells as $table_cell ){
					?>
					<td><?php echo $table_cell ?></td>
					<?php
				}
				?>
			</tr>
			<?php
		}
		?>
		</table>
		<?php
		$table = ob_get_contents();
		ob_end_clean();
		return $table;
	}

	function parse_media( $media_id = 0, $field = FALSE ){
		if( !is_numeric( $media_id ) ){
			return $media_id;
		}
		if( !isset( $this->__categories_table_cache__ ) ){
			$results = $this->query("SELECT id_document AS media_id, fichier AS file, largeur AS width, hauteur AS height, BINARY titre AS title, date FROM spip_documents;");
			while( $results and $row = mysql_fetch_assoc( $results ) ){
				$row['filename'] = $row['file'];
				$row['url'] =  $this->imported_setup['adresse_site'] . '/' . trim( $this->imported_setup['dir_img'], '/' ). '/' . trim( $row['filename'], '/' );
				$row['original_url'] = $row['url'];
				if( $this->do_importing( 'media', $media_id ) ){
					$tmp = download_url( $x = $row['original_url'] );
/*debug*/					die('#'.var_export(compact('media_id','row','tmp','x')));					
					if( is_wp_error( $tmp ) ){
						@unlink( $tmp );
						return $tmp;
					}
					$file = array(
						'name'	=>	basename( $row['original_url'] ),
						'tmp_name'	=>	$tmp
						)
					;
					$handle = media_handle_sideload( $file, 0 );
					if( is_wp_error( $handle ) ){
						@unlink( $file['tmp_name']);
						return $handle;
					}
					$row['url'] = wp_get_attachment_url( $handle );
				}
				$row['file'] = $row['url'];
				$this->__categories_table_cache__[ $row['media_id'] ] = $row;
			}
		}
		$media = ( array_key_exists( $media_id, $this->__categories_table_cache__) ) ? $this->__categories_table_cache__[ $media_id ] : array();
		if( $field and array_key_exists( $field, $media ) ){
			$media = $media[ $field ];
		}
		return empty( $media ) ? FALSE : $media;
	}

	function parse_link( $id, $type = 'article' ){
		global $wpdb;
		if( $type == 'post' ){
			$type = 'article';
		}
		if( $type == 'media' ){
			$type = 'document';
		}
		if( $type == 'document' ){
			return $this->parse_media( $id, 'src' );
		}
		if( $type == 'article' ){
			$spip_article = $this->sql("SELECT id_article AS spip_id, BINARY titre as title FROM spip_articles", "spip_id", $id);
			if( empty( $spip_article['title'] ) ){
				return $id;
			}
			$wp_post = get_page_by_title( $spip_article['title'], 'OBJECT', 'post' );
			if( empty( $wp_post ) ){
				return $this->imported_setup['adresse_site'] . '/article' .  $spip_article['spip_id'];
			}
			return get_permalink( $wp_post->ID );
		}
	}

	function parse_filter( $filters ){
		$filters = explode('|', $filters );
		return 'class="' . implode( ' ' , $filters ) . '"';
	}

	function sql( $SQL, $index = NULL, $match = FALSE ){
		if( !is_string( $SQL ) ){
			return NULL;
		}
		if( isset( $this->{'__'. sanitize_key( md5( $SQL ) ) .'_table_cache__'} ) ){
			$dataset = $this->{'__'. sanitize_key( md5( $SQL ) ) .'_table_cache__'};
		}
		else{
			$results = mysql_query( $SQL );
			while( $results and $row = mysql_fetch_assoc( $results ) ){
				if( empty( $index ) ){
					$dataset[] = $row;
				}
				else{
					$dataset[ $row[ $index ] ] = $row;
				}
			}
		}
		if( $match ){
			return $dataset[ $match ];
		}
		return $dataset;
	}

	function get_authors( $id_article ){
		$results = $this->query("SELECT spip_auteurs_articles.id_article AS id, spip_auteurs.email FROM spip_auteurs_articles,spip_auteurs WHERE spip_auteurs.id_auteur = spip_auteurs_articles.id_auteur AND spip_auteurs_articles.id_article = $id_article;
");
		$authors = array();
		while( $row = mysql_fetch_assoc( $results ) ){
			$authors[] = $row['email'];
		}
		return $authors;
	}

	function get_keywords( $id_article = NULL ){
		$results = $this->query("SELECT BINARY titre AS tag FROM spip_mots_articles,spip_mots WHERE id_article=$id_article AND spip_mots_articles.id_mot=spip_mots.id_mot;");
		$tags = array();
		while( $results and $row = mysql_fetch_assoc( $results ) ){
			$tags[] = $row['tag'];
		}
		return $tags;
	}

	function get_categories_table( $field = NULL, $value = NULL ){
		if( !is_array( $this->__categories_table_cache__ ) ){
			$this->__categories_table_cache__ = array();
			$results = $this->query("SELECT id_rubrique AS spip_id, BINARY titre AS category, (SELECT BINARY titre FROM spip_rubriques AS spip_parent_rubriques WHERE spip_parent_rubriques.id_rubrique = spip_rubriques.id_parent) AS parent FROM spip_rubriques;");
			while( $row = mysql_fetch_assoc( $results ) ){
				$this->__categories_table_cache__[ $row['spip_id'] ] = $row;
			}
		}
		if( isset( $field ) ){
			foreach( $this->__categories_table_cache__ as $index => $row ){
				if( isset( $row[ $field ] ) and $row[ $field ] == $value ){
					return $row;
				}
			}
			return FALSE;
		}
		return $this->__categories_table_cache__;
	}

	function get_category_chain( $id_category ){
		$field = 'spip_id';
		$value = $id_category;
		while( $current = $this->get_categories_table( $field, $value ) ){
			$field = 'category';
			$value = $current['parent'];
			$category_chain[] = $current['category'];
		}
		return $category_chain;
	}

	function test_connection(){
		$options = $this->options;
		if( $options['imported_setup'] = $this->imported_setup ){
			return true;
		}
		add_settings_error( 'spip_import', 'connection-faillure',  __('Invalid connection settings') . ': ' . mysql_error(), 'error' );
		return false;

	}

	function reset_connection(){
		$options = $this->options;
		$options['connection'] = NULL;
		$options['imported_setup'] = NULL;
		update_option( 'spip_import', $options );
	}

	function media_tag( $media_tag ){
		return $media_tag;
	}

	function do_importing( $type, $id = 'import__all' ){
		$do_importing = empty( $this->options['do_importing'] ) ? array() : $this->options['do_importing'];
		if( array_key_exists( 'import__all' , $do_importing ) and $do_importing['import__all'] ){
			return TRUE;
		}
		if( array_key_exists( $type, $do_importing ) and is_array( $do_importing[ $type ] ) ){
			if( array_key_exists( $id , $do_importing[ $type ] ) and $do_importing[ $type ][ $id ] ){
				return TRUE;
			}
			elseif( array_key_exists( 'import__all' , $do_importing[ $type ] ) and $do_importing[ $type ][ 'import__all' ] ){
				return TRUE;
			}
			else{
				return FALSE;
			}
		}
	}

}
$spip_importer = new SpipMediaImporter();
?>