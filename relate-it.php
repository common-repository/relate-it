<?php
 
/**
*
* Plugin Name: Relate-it
* Plugin URI: http://wordpress.org/support/profile/bachiller
* Description: A plugin that shows a thumbnail of related posts from the actual post based on different criteria.
* Version: 1.0
* Author: Jose Luis Bachiller
* Author URI: http://wordpress.org/support/profile/bachiller
* License: GPL2
*
*/

/*
Copyright (C) 2008-2009 Michael Torbert, semperfiwebdesign.com (michael AT semperfiwebdesign DOT com)
Original code by uberdose of uberdose.com

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/




//All the defined values can be set from the settings menu for the admin user
define ( 'CAPTION', 'You may also like:' ); // Default caption for the thumbnail section
define ( 'MAX_TITLE_LENGHT', 55 ); //Default related posts title max length to avoy it overflow the reserved space
define ( 'RELATED_POSTS', 5 ); // Default number of related posts to display.



/**
 * Main function of the plugin.
 *
 * This function checks if a post is being shown, and if it's true then shows the plugin caption and call the function @link relate_it_plugin_generate_links()
 * to generate a thumbnail of related posts.
 *
 * @see relate_it_plugin_generate_links()
 * @since 3.6.0
 * @param string $content Buffer of the content of the post.
 * @return string Buffer of the content of the post with the adition of the code generated.
 */
function relate_it_plugin_below_post( $content ) {
	$relateit_output = '';
	if ( !is_feed() && !is_page() && is_single() ) { // If we are watching the detail of a post we'll have to work.

		// Next two lines allows the plugin to use it's own css stylesheet. To use the main css these lines should be commented.
		wp_register_style( 'RelateIt-Plugin', plugins_url( 'css/style.css' , __FILE__ ) );
		wp_enqueue_style( 'RelateIt-Plugin' );

		// Now we'll show the caption option and then we'll call a function to generate the thumbnail of related posts
	        $relateit_output .= '<div class="RelateIt-Plugin"><h3>'.get_option('RelateIt_caption',CAPTION).'</h3>';
		$relateit_output .= relate_it_plugin_generate_links();
		$relateit_output .= '</div>';
		$relateit_output .= '<div class="clear"></div>';
	}

	// All the generated content will be shown after the content Wordpress provide the plugin (below the post)
	return $content.$relateit_output;
}



/**
 * Function that obtains the related posts and calls the function @link relate_it_plugin_buffer_related_posts() to generate the output.
 *
 * This funcion has the logic to generate the querys to obtain the related posts. First it obtains the tags of the main post and then looks for
 * posts with same tags. If the number of obtained posts doesn't reach the RELATED_POSTS value (or it's configured value from the admin section)
 * then it takes the categories from the main post and looks for posts with the same categories. Finally, to complete the number of related posts,
 * it takes random post (even if they are no related by tags or categories).
 *
 * @see relate_it_plugin_buffer_related_posts()
 * @global object $post Reference to the main post.
 *
 * @access private
 * @since 3.6.0
 *
 * @return string Buffer with the HTML code that includes a thumbnail with the related posts of the global $post
 */
function relate_it_plugin_generate_links() {
	global $post;
	$posts_mostrados = array( 'this'=>$post->ID ); //Variable to control number of shown elements and to control they don't repeat. The first one is the main post.
	$output = '';


	// First we are going to try to show post with a common tag with the main post
	// So we have to obtain the tags from the main post
	$tags = wp_get_post_tags( $post->ID );
	if ( $tags ) {
		// Let's create an array with main post tags
		$tag_ids = array();
		foreach( $tags as $individual_tag ) $tag_ids[] = $individual_tag->term_id;  

		// Now we are ready to build a query looking for the posts with one of the tags from the created array
		$args = array(
			'tag__in' => $tag_ids,
			'post__not_in' => $posts_mostrados,
			'posts_per_page'=> (get_option( 'RelateIt_number_related_posts' , RELATED_POSTS )-count($posts_mostrados)+1), 
			'caller_get_posts'=> 1
			);
		$resultado_consulta = relate_it_plugin_buffer_related_posts( $args );
		$posts_mostrados = array_merge( $posts_mostrados, $resultado_consulta['ids_related_posts'] );
		$output .= $resultado_consulta['buffer'];
	}


	// If we didn't get enought posts with tags criteria, we'll look for them throught related categories
	if ( count( $posts_mostrados ) <= get_option( 'RelateIt_number_related_posts', RELATED_POSTS ) ) {
		// We have to build an array with the IDs of all the categories from the main post
		$categorias = get_the_category();
		$categorias_id = array();
		foreach ( $categorias as $categoria ) $categorias_id[] = $categoria->cat_ID;

		// Now we are ready to build a query looking for the posts with one of the categories from the created array
		$args = array(
			'category__in' => $categorias_id,
			'post__not_in' => $posts_mostrados,
			'posts_per_page'=> (get_option( 'RelateIt_number_related_posts' , RELATED_POSTS )-count($posts_mostrados)+1),   
			'caller_get_posts'=> 1
			);

		$resultado_consulta = relate_it_plugin_buffer_related_posts( $args );
		$posts_mostrados = array_merge( $posts_mostrados, $resultado_consulta['ids_related_posts'] );
		$output .= $resultado_consulta['buffer'];

	}

	// And finally, if we don't have enougth posts we'll select some random ones
	if ( count($posts_mostrados) <= get_option( 'RelateIt_number_related_posts', RELATED_POSTS ) ) {
		$args=array(
		'post__not_in' => $posts_mostrados,
		'orderby' => 'rand',
		'posts_per_page'=> (get_option( 'RelateIt_number_related_posts', RELATED_POSTS )-count($posts_mostrados)+1 ),
		'caller_get_posts'=> 1
		);

		$resultado_consulta = relate_it_plugin_buffer_related_posts( $args );
		$posts_mostrados = array_merge( $posts_mostrados, $resultado_consulta['ids_related_posts'] );
		$output .= $resultado_consulta['buffer'];
	}

	return $output;
}



/**
 * This function executes a query and returns the HTML code and the IDs resulting from the query.
 *
 * With the appropiate parameters, this function executes a Wordpress query to obtain a list of posts. Then it generates an array
 * with the resulting IDs and a piece of HTML code to show the name, link and image of the result. 
 *
 * @link http://codex.wordpress.org/Class_Reference/WP_Query Class reference of WP_Query.
 * @global object $post Reference to the main post.
 * @access private
 * @since 3.6.0
 *
 * @param array $parametros_consulta Parameters of the wordpress posts query acording to WP_query WordPress class.
 * @return array {
 * 	List of posts IDs and buffer with HTML code.
 *	@type array 'ids_related_posts' Array with the list of posts IDs returned by the query.
 *	@type string 'buffer' Buffer with the HTML code to show the name, link and image of the resulting posts.
 * }
 */
function relate_it_plugin_buffer_related_posts( $parametros_consulta ) {
	global $post;

	$orig_post = $post; // The main post reference is saved to recover it after the query is made. 
	$posts_resultantes = array();
	$contenido_a_mostrar ='';
	$my_query = new wp_query( $parametros_consulta );
 	while( $my_query->have_posts() ) {
		$my_query -> the_post();
		$posts_resultantes[] = get_the_ID();
		$contenido_a_mostrar .= '<div class="RelateIt-thumb">';
		$contenido_a_mostrar .= '<a rel="external" href="'.get_permalink().'">';
		$contenido_a_mostrar .= '<div class="RelateIt-thumb-image">';
		$contenido_a_mostrar .= get_the_post_thumbnail( get_the_ID() );
		$contenido_a_mostrar .= '</div>';

		// If the post title is to long it will be truncate.
		$titulo_post_relacionado = trim( the_title( '', '', false ) );
		if (strlen($titulo_post_relacionado) >= get_option( 'RelateIt_max_title_length', MAX_TITLE_LENGHT )+2 )
			$contenido_a_mostrar.= substr( $titulo_post_relacionado, 0, get_option( 'RelateIt_max_title_length', MAX_TITLE_LENGHT ) ).'...';
		else $contenido_a_mostrar .= $titulo_post_relacionado;
		$contenido_a_mostrar .= '</a>';
		$contenido_a_mostrar .= '</div>';
	}

	wp_reset_query();
	$post = $orig_post;
	return array(
		'ids_related_posts' => $posts_resultantes,
		'buffer' => $contenido_a_mostrar
		);

}



/**************************
/*    Admin section       *
/**************************


/**
 * Add the plugin settings page to the admin settings menu.
 *
 * @link http://codex.wordpress.org/Function_Reference/add_options_page Wordpress reference of add_options_page
 *
 * @access private
 * @since 3.6.0
 *
 * @return void
 */
function relate_it_plugin_admin() {
	add_options_page( 'Relate-it plugin settings', 'Relate-it plugin', 'manage_options', 'RelateIt_settings_menu', 'relate_it_plugin_option' );
}



/**
 * Function to manage the plugin settigns.
 *
 * This function creates and manage a form for the general settings of the plugin. This form is shown
 * in the settings section of the WordPress admin user.
 *
 * @access private
 * @since 3.6.0
 *
 * @return void
 */
function relate_it_plugin_option () {
	if ( !current_user_can( 'manage_options' ) ) {
        	wp_die( __('Access denied.') );
    	}

	echo '<div class="wrap">';
	echo '<div id="icon-options-general" class="icon32"><br></div>';
	echo '<h2>' . esc_attr__( 'Settings' ) . ' Relate-it plugin</h2>';

	// If any of the settings have been posted the options should be updated
	if( isset( $_POST[ 'RelateIt_caption' ] ) ) {
		echo '<div class="updated"><p><strong>'.esc_attr__( 'Settings saved.' ).'</strong></p></div>';

		if( isset($_POST[ 'RelateIt_caption' ]) ) update_option( 'RelateIt_caption', $_POST['RelateIt_caption'] );
		if( isset($_POST[ 'RelateIt_max_title_length' ]) ) update_option(' RelateIt_max_title_length', $_POST['RelateIt_max_title_length'] );
		if( isset($_POST[ 'RelateIt_number_related_posts' ]) ) update_option( 'RelateIt_number_related_posts', $_POST['RelateIt_number_related_posts'] );
	}

        ?>

	<form name="form1" method="post" action="">
		<table class="form-table">
		<tbody>
		<tr valign="top">
			<th scope="row"><label for="RelateIt_caption"><?php _e( 'Caption: ', 'RelateIt_settings_menu' ); ?></label></th>
			<td><input type="text" name="RelateIt_caption" value="<?php echo get_option( 'RelateIt_caption',CAPTION ); ?>" size="50" maxlength="50" /></td>
		</tr>
                <tr valign="top">
			<th scope="row"><label for="RelateIt_max_title_length"><?php _e( 'Maximun lenght of related post title: ', 'RelateIt_settings_menu' ); ?></label></th>
			<td><input type="text" name="RelateIt_max_title_length" value="<?php echo get_option( 'RelateIt_max_title_length',MAX_TITLE_LENGHT ); ?>" size="2" maxlength="2" /></td>
		</tr>
                <tr valign="top">
			<th scope="row"><label for="RelateIt_number_related_posts"><?php _e( 'Number of related posts: ', 'RelateIt_settings_menu' ); ?></label></th>
			<td><input type="text" name="RelateIt_number_related_posts" value="<?php echo get_option( 'RelateIt_number_related_posts' , RELATED_POSTS );?>" size="2" maxlength="2" /></td>
		</tr>
		</tbody>
		</table>

            <p class="submit">
                <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes' ) ?>" />
            </p>
        </form>
    </div>

<?php
}



// WordPress will call the plugin when the content is shown
add_filter( 'the_content', 'relate_it_plugin_below_post' );

// WordPress will add the plugin to the admin section
add_action( 'admin_menu',  'relate_it_plugin_admin' );
?>
