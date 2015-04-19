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
			return get_option( 'spip_media_import', array() );
		}
		elseif( $property == 'connection' ){
			$link = NULL;
			$options = $this->options;
			if( function_exists('mysql_connect') ){
				$link = mysql_connect(
					$this->options['connection']['host'],
					$this->options['connection']['username'],
					$this->options['connection']['password']
					)
				;
				if( !mysql_select_db( $this->options['connection']['database'], $link ) ){
					$link = FALSE;
				}
			}
			else{
				?>
				<div id="spip-media-import-disconnected" class="error settings-error">
					<p><strong><?php echo __('MySQL library is missing') ?></strong></p>
				</div>
				<?php
			}
			$options['imported_setup'] = array();
			$results = mysql_query( "SELECT * FROM spip_meta;" );
			while( $row = mysql_fetch_assoc( $results ) ){
				$options['imported_setup'][ $row['nom'] ] = $row['valeur'];
			}
			update_option( 'spip_media_import', $options );
			return $link;
		}
		else{
			/*DEBUG*/die('erro grave: ' . compact('property'));
		}
	}

	function plugin_options(){
		add_settings_section(
			'media_import_connection_section',
			__('SPIP Media Importer Connection Credentials'),
			array( $this, 'media_import_section_callback'),
			'media'
			)
		;
		register_setting(
			'media',
			'spip_media_import',
			array( $this, 'media_import_options_check' )
			)
		;
		if( !$this->connection ){
			add_settings_field(
				'connection_host',
				__('Host'),
				array( $this, 'media_import_connection_host' ),
				'media',
				'media_import_connection_section'
				)
			;
			add_settings_field(
				'connection_database',
				__('Database Name'),
				array( $this, 'media_import_connection_database' ),
				'media',
				'media_import_connection_section'
				)
			;
			add_settings_field(
				'connection_user',
				__('Username'),
				array( $this, 'media_import_connection_username' ),
				'media',
				'media_import_connection_section'
				)
			;
			add_settings_field(
				'connection_password',
				__('Password'),
				array( $this, 'media_import_connection_password' ),
				'media',
				'media_import_connection_section'
				)
			;
		}
		else{
			add_settings_field(
				'connection_reset',
				__('Erase connection data') . " <code style=\"font-weight: normal;\">{$this->options[connection][username]}@{$this->options[connection][host]}</code>",
				array( $this, 'media_import_connection_reset' ),
				'media',
				'media_import_connection_section'
				)
			;
			add_settings_field(
				'database_users',
				__('Import this Users'),
				array( $this, 'media_import_users' ),
				'media',
				'media_import_connection_section'
				)
			;
			add_settings_field(
				'database_tags',
				__('Import this tags'),
				array( $this, 'media_import_tags' ),
				'media',
				'media_import_connection_section'
				)
			;
			add_settings_field(
				'database_tags',
				__('Import this categories'),
				array( $this, 'media_import_categories' ),
				'media',
				'media_import_connection_section'
				)
			;
			add_settings_field(
				'database_posts',
				__('Import this Posts'),
				array( $this, 'media_import_posts' ),
				'media',
				'media_import_connection_section'
				)
			;
/*			add_settings_field(
				'database_content',
				__('Database content'),
				array( $this, 'media_import_database_content' ),
				'media',
				'media_import_connection_section'
				)
			;
*/		}
	}

	function media_import_users(){
		$results = $this->query( "SELECT BINARY login AS user_login, BINARY nom AS display_name, BINARY email AS user_email FROM spip_auteurs;" );
		?>
		<dl>
		<?php
		while( $results and $row = mysql_fetch_assoc( $results ) ){
			extract( $row );
			?>			
				<dt><?php echo ( empty( $display_name ) ? $user_login : $display_name ) ?></dt>
				<dd><strong>username: </strong><?php echo $user_login ?></dd>
				<dd><strong>email: </strong><?php echo $user_email ?></dd>
			<?php
		}
		?>
		</dl>
		<?php
	}

	function media_import_tags(){
		$results = $this->query( "SELECT id_mot AS spip_id, BINARY titre AS tag, CONCAT( BINARY descriptif, IF(  ( descriptif != '' AND texte != ''), '\n', '' ), BINARY texte ) AS description  FROM spip_mots;" );
		?>
		<ul>
			<?php
		while( $results and $row = mysql_fetch_assoc( $results ) ){
			extract( $row );
			?>
			<li><strong><?php echo $tag; ?></strong> <em><?php echo $description; ?></em></li>
			<?php
		}
			?>
		</ul>
		<?php
	}

	function media_import_categories(){
		$results = $this->query( "SELECT id_parent AS id_parent_category , BINARY titre AS category,CONCAT( BINARY descriptif, IF(  ( descriptif != '' AND texte != ''), '\n', '' ), BINARY texte ) AS description, (SELECT BINARY titre FROM spip_rubriques WHERE id_rubrique = id_parent_category ) AS parent_category FROM spip_rubriques;" );
		?>
		<ul>
			<?php
		while( $results and $row = mysql_fetch_assoc( $results ) ){
			extract( $row );
			?>
			<li><strong><?php echo $category; ?></strong> <em><?php echo $description; ?></em><?php echo $id_parent_category ? " [child of <em>$parent_category</em>]" : "" ?></li>
			<?php
		}
			?>
		</ul>
		<?php
	}

	function media_import_posts(){
		$results = $this->query( "SELECT id_article AS spip_id, BINARY titre AS post_title, BINARY texte AS post_content, statut AS post_status, date AS post_date FROM spip_articles;" );
		?>
		<ul>
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
					<hr>
				</li>
				<?php
			}
			?>
		</ul>
		<?php
	}


	function media_import_section_callback( $media_tags ){
		$link = $this->connection;
		if( $link ){
			?>
			<div id="spip-media-import-connected" class="updated settings-error">
				<p><strong><?php echo __('Successfully connected to') . ' <a href="' . $this->options['imported_setup']['adresse_site'] . '">' . $this->options['imported_setup']['adresse_site'] . '</a> (database: ' . $this->options['connection']['username'] . '@' . $this->options['connection']['host'] . ')' ?></strong></p>
			</div>
			<?php
		}
		else{
			?>
			<div id="spip-media-import-disconnected" class="error settings-error">
				<p><strong><?php echo __('Failed connecting to') . ' ' . $this->options['connection']['host'] . '. ' . __('Please, check your DB settings.') ?></strong></p>
			</div>
			<?php
		}
		?>
		<style type="text/css">
		.footer{
			background-color: #DDD;
		}
		</style>
		<?php
	}

	function media_import_options_check( $input ){
		if( !isset( $input['connection']['reset'] ) ){
			if( empty( $input['connection']['host'] ) ){
				add_settings_error( 'spip_media_import', 'connection-host', __('Please set the server host'), 'error' );
			}
			if( empty( $input['connection']['username'] ) ){
				add_settings_error( 'spip_media_import', 'connection-username', __('You must set a username'), 'error' );
			}
			if( empty( $input['connection']['password'] ) ){
				add_settings_error( 'spip_media_import', 'connection-password', __('Password can not be empty'), 'error' );
			}
			$input['connection']['reset'] = FALSE;
		}
		elseif( $input['connection']['reset'] ){
			$options = $this->options;
			$options['connection'] = NULL;
			update_option( 'spip_media_import', $options );
		}
		return $input;
	}

	function media_import_connection_host(){
		$host = ( isset( $this->options['connection'] ) and isset( $this->options['connection']['host'] ) ) ? $this->options['connection']['host'] : NULL;
		?>
		<input type="text" id="media_import_connection_host" name="spip_media_import[connection][host]"<?php if( isset( $host ) ) echo " value=\"$host\""; ?>>
		<?php
	}

	function media_import_connection_database(){
		$database = ( isset( $this->options['connection'] ) and isset( $this->options['connection']['database'] ) ) ? $this->options['connection']['database'] : NULL;
		?>
		<input type="text" id="media_import_connection_database" name="spip_media_import[connection][database]"<?php echo ( $database ? " value=\"$database\"" : "" ); ?>>
		<?php
	}

	function media_import_connection_username(){
		$username = ( isset( $this->options['connection'] ) and isset( $this->options['connection']['username'] ) ) ? $this->options['connection']['username'] : NULL;
		?>
		<input type="text" id="media_import_connection_username" name="spip_media_import[connection][username]"<?php echo ( $username ? " value=\"$username\"" : "" ); ?>>
		<?php
	}

	function media_import_connection_password(){
		$password = ( isset( $this->options['connection'] ) and isset( $this->options['connection']['password'] ) ) ? $this->options['connection']['password'] : NULL;
		?>
		<input type="password" id="media_import_connection_password" name="spip_media_import[connection][password]"<?php echo ( $password ? " value=\"$password\"" : "" ); ?>>
		<?php
	}

	function media_import_connection_reset(){
		$reset = ( isset( $this->options['connection'] ) and isset( $this->options['connection']['reset'] ) and $this->options['connection']['reset'] );
		?>
		<input type="checkbox" id="media_import_connection_reset" name="spip_media_import[connection][reset]"<?php echo ( $reset ? " checked" : "" ); ?>>
		<?php
	}

	function media_import_database_content(){
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
		$connection = $this->connection;
		$results = mysql_query( $SQL, $connection );
		if( !$results ){
			echo mysql_error();
		}
		return $results;
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
		$media['file'] =   $this->options['imported_setup']['adresse_site'] . '/' . $this->options['imported_setup']['dir_img'] . '/' .  $media['file'];
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

	function get_categories( $id_article ){

	}

	function media_tag( $media_tag ){
		return $media_tag;
	}

}
$spip_media_importer = new SpipMediaImporter();
?>