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
			/*DEBUG*/die('erro grave: ' . var_export( compact('property') ) );
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
				$tag = wp_insert_term( array(
					$tag,
					'post_tag',
					array(
						'description'	=>	$description
						)
					)
				);
				if( is_wp_error( $tag ) ){
					$errors = $user->get_error_messages();
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
				$category = wp_insert_term( array(
					$category,
					'category',
					array(
						'description'	=>	$description
						)
					)
				);
				if( is_wp_error( $category ) ){
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
					<input id="spip_import_post_import__all" name="spip_import[do_importing][post][import__all]">
				</label>
			</li>
			<?php
			while( $results and $row = mysql_fetch_assoc( $results ) ){
				extract( $row );
				$wp_staus = array( 'publie' => 'publish', 'prepa' => 'draft', 'prop' => 'pending' );
				$post_status = $wp_staus[ $post_status ];
				$post_author = implode( ',' , $this->get_authors( $spip_id ) );
				?>
				<li>
					<h1 class="header"><?php echo $post_title; ?></h1>
					<?php if( isset( $post_excerpt ) ){
						?>
					<p class="content"><em><?php $this->parse_content( $post_excerpt ) ?></em></p>
						<?php
					}
					?>
					<p class="content"><?php echo $this->parse_content( $post_content ) ?></p>
					<p class="footer">
						<em><?php echo $post_date; ?></em> 
						<em><?php echo __( $post_status ); ?></em> 
						<em><strong><?php echo $post_author; ?></strong></em>
						[<?php echo $spip_id ?>]
					</p>
					<p><em><?php echo implode( '</em> <strong>-&gt;</strong> <em>' , $this->get_category_chain( $category ) ) ?></em></p>
					<hr>
				</li>
				<?php
				if( $this->do_importing( 'user', $user_login ) ){
					;
				}
			}
			?>
		</ul>
		<?php
	}

	function spip_import_media(){

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
#		$content = preg_replace( '/\{{6}(?P<content>.*?)\}{6}/m', "<h6>$1</h6>", $content );
#		$content = preg_replace( '/\{{5}(?P<content>.*?)\}{5}/m', "<h5>$1</h5>", $content );
#		$content = preg_replace( '/\{{4}(?P<content>.*?)\}{4}/m', "<h4>$1</h4>", $content );
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

	function parse_media( $media_id ){
		$results = $this->query("SELECT fichier AS file, largeur AS width, hauteur AS height, titre AS title, date FROM spip_documents WHERE id_document = $media_id");
		$media = mysql_fetch_assoc( $results );
		$media['file'] =   $this->imported_setup['adresse_site'] . '/' . $this->imported_setup['dir_img'] . '/' .  $media['file'];
		return $media;
	}

	function parse_filter( $filters ){
		$filters = explode('|', $filters );
		return 'class="' . implode( ' ' , $filters ) . '"';
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

	}

	function get_categories_table(){
		$results = $this->query("SELECT id_rubrique AS spip_id, BINARY titre AS category, (SELECT BINARY titre FROM spip_rubriques AS spip_parent_rubriques WHERE spip_parent_rubriques.id_rubrique = spip_rubriques.id_parent) AS parent FROM spip_rubriques;");
		$categories = array();
		while( $row = mysql_fetch_assoc( $results ) ){
			$categories[ $row['spip_id'] ] = array( 'category' => $row['category'], 'parent' => $row['parent'] );
		}
		return $categores;
	}

	function get_category_chain( $id_category ){
		$categories_table = $this->get_categories_table();
		$category = isset( $categories_table[ $id_category ] ) ? $categories_table[ $id_category ] : array();
		$category_chain = array( $category['category'] );
		while( array_key_exists( 'parent' , $category ) ){
			$category = $category['parent'];
			$category_chain[] = $category['category'];
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

	function do_importing( $type, $id ){
		$do_importing = $this->options['do_importing'];
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