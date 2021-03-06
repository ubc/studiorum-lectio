<?php

	/**
	 * An add-on for Studiorum
	 *
	 * @package     Lectio
	 * @subpackage
	 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
	 * @since       0.1.0
	 */

	// Exit if accessed directly
	if( !defined( 'ABSPATH' ) ){
		exit;
	}

	class Studiorum_Lectio extends Studiorum_Addon
	{


		/**
		 * Actions and filters
		 *
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		public function __construct()
		{

			// Load our necessary post types and taxonomies
			add_action( 'after_setup_theme', array( $this, 'after_setup_theme__includes' ), 1 );

			// // We need for students to be able to edit the page on which the upload form is on - so they can add media to the WYSIWYG
			add_filter( 'user_has_cap', array( $this, 'user_has_cap__giveStudentAbilityToEditFormPage' ), 100, 3 );

			add_filter( 'user_has_cap', array( $this, 'user_has_cap__alterSubmissionsVisibility' ), 100, 3 );

			// // Remove 'private' and 'protected' from titles
			add_filter( 'private_title_format', array( $this, 'title_format__removePrivatePublicFromTitle' ) );
			add_filter( 'protected_title_format', array( $this, 'title_format__removePrivatePublicFromTitle' ) );

			// // Prevent Edit/New links in admin bar for student
			add_action( 'wp_before_admin_bar_render', array( $this, 'wp_before_admin_bar_render__removeAdminBarLinksForStudents' ) );

			// Add the editor styles, hopefully making it easier for a user to create content as it will be produced
			add_action( 'init', array( $this, 'init__addEditorStyles' ), 999 );

			// Add some inline styles
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts__inlineStyles' ) );

			// Some extra styles for warning messages etc.
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts__frontEndStyles' ) );

			// Some styles for th back-end
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts__backEndStyles' ) );

			// When a student logs in, redirect them to either a redirect_to url or the home page, not the back end
			add_filter( 'login_redirect', array( $this, 'login_redirect__studentDoesNotLogInToBackEnd' ), 10 ,3 );

			// Register ourself as an addon
			add_filter( 'studiorum_modules', array( $this, 'studiorum_modules__registerAsModule' ) );

			// Add a print stylesheet for the new endpoint /print/
			add_action( 'template_redirect', array( $this, 'template_redirect__addPrintLayout' ) );

		}/* __construct() */


		/**
		 * Load our includes
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		public static function after_setup_theme__includes()
		{

			require_once( trailingslashit( LECTIO_PLUGIN_DIR ) . 'includes/class-studiorum-lectio-utils.php' );
			require_once( trailingslashit( LECTIO_PLUGIN_DIR ) . 'includes/admin/class-studiorum-lectio-settings.php' );
			require_once( trailingslashit( LECTIO_PLUGIN_DIR ) . 'includes/class-studiorum-lectio-post-type.php' );
			require_once( trailingslashit( LECTIO_PLUGIN_DIR ) . 'includes/class-studiorum-lectio-taxonomy-meta.php' );
			require_once( trailingslashit( LECTIO_PLUGIN_DIR ) . 'includes/class-studiorum-lectio-taxonomies.php' );
			require_once( trailingslashit( LECTIO_PLUGIN_DIR ) . 'includes/gravity-forms-hooks/class-studiorum-lectio-gravity-forms-hooks.php' );

			if( !is_admin() ){
				return;
			}

			require_once( trailingslashit( LECTIO_PLUGIN_DIR ) . 'includes/admin/class-studiorum-lectio-educator-dashboard.php' );
			require_once( trailingslashit( LECTIO_PLUGIN_DIR ) . 'includes/admin/class-studiorum-lectio-student-dashboard.php' );

		}/* after_setup_theme__includes() */


		/**
		 * As we want students to be able to upload images when they are using the WYSIWYG, we need to ensure they have the capbility
		 * to 'edit' the page on which the form is located. (i.e. when they use the media uploader, it attaches the media to that page,
		 * so they basically need the ability to edit it - from the front end. We also need to stop them from being able to edit the
		 * actual content of the page itself)
		 *
		 * @since 0.1
		 *
		 * @param array $capauser All the capabilities of the user
		 * @param array $capask   [0] Required capability
		 * @param array $param    [0] Requested capability
		 *                        [1] User ID
		 *                        [2] Associated object ID
		 * @return array $capauser All the capabilities of the user
		 */

		public function user_has_cap__giveStudentAbilityToEditFormPage( $capauser, $capask, $param )
		{

			// global $wpdb;

			// Ensure we're talking about post/page capabilities and that we have a post/page
			if( !isset( $param[2] ) ){
				return $capauser;
			}

			// This only applies to students
			if( !isset( $capauser['studiorum_student'] ) || ( isset( $capauser['studiorum_student'] ) && $capauser['studiorum_student'] != 1 ) ){
				return $capauser;
			}

			// Which posts/pages is the user able to edit
			// Fetch from the option and run through a filter
			$selectedPages = get_studiorum_option( 'lectio_options', 'posts_containing_forms' );
			$allowedPostIDsForCurrentUser = apply_filters( 'studiorum_lectio_post_ids_to_allow_students_to_upload_media', array_values( $selectedPages ), $capauser, $capask, $param );

			// Grab the current post/page
			$post = get_post( intval( $param[2] ) );

			if( !$post || !isset( $post->ID ) ){
				return $capauser;
			}

			// Is this post ID in the allowed array? If not, bail
			if( !in_array( $post->ID,  $allowedPostIDsForCurrentUser ) ){
				return $capauser;
			}

			// Add the capability temporarily to this user to be able to edit this page
			$capauser['edit_others_posts'] 		= 1;
			$capauser['edit_published_pages'] 	= 1;
			$capauser['edit_published_posts'] 	= 1;

			return $capauser;

		}/* user_has_cap__giveStudentAbilityToEditFormPage() */


		/**
		 * When a submission is made, the posts are private. By default this means only the author and admins can see the posts
		 * We run this method to ensure that we can modify this behaviour on a post-by-post basis from elsewhere
		 *
		 * @since 0.1
		 *
		 * @param array $capauser All the capabilities of the user
		 * @param array $capask   [0] Required capability
		 * @param array $param    [0] Requested capability
		 *                        [1] User ID
		 *                        [2] Associated object ID
		 * @return array $capauser All the capabilities of the user
		 */

		public function user_has_cap__alterSubmissionsVisibility( $capauser, $capask, $param )
		{

			// Ensure we're talking about post/page capabilities and that we have a post/page
			if( !isset( $param[2] ) ){
				return $capauser;
			}

			// This only applies to students
			if( !isset( $capauser['studiorum_student'] ) || ( isset( $capauser['studiorum_student'] ) && $capauser['studiorum_student'] != 1 ) ){
				return $capauser;
			}
			// Grab the post/page
			$post = get_post( intval( $param[2] ) );

			// Ensure this is a submission
			if( !$post || !isset( $post->post_type ) || ( isset( $post->post_type ) && $post->post_type != 'lectio-submission' ) ){
				return $capauser;
			}

			$currentUserID = get_current_user_id();

			// The post author should be able to see this by default
			if( !$post->post_author || $currentUserID == $post->post_author ){
				return $capauser;
			}

			// Specific user IDs that are able to see this post
			$specificUsersAbleToSeeThisPost = apply_filters( 'studiorum_lectio_specific_users_who_can_see_private_submissions', array(), $currentUserID, $post );


			// As these are private posts by default, we need to ensure that the users are able to view them and this post specifically
			if( !in_array( $currentUserID, $specificUsersAbleToSeeThisPost ) ){
				return $capauser;
			}

			$capauser['read_private_posts'] = 1;
			$capauser['read_post'] = 1;

			return $capauser;

		}/* user_has_cap__alterSubmissionsVisibility() */


		/**
		 * Remove the words 'Private' and 'Protected' from titles
		 *
		 * @since 0.1
		 *
		 * @param string $content the title content
		 * @return string The modified title
		 */

		public function title_format__removePrivatePublicFromTitle( $content )
		{

			return '%s';

		}/* title_format__removePrivatePublicFromTitle() */


		/**
		 * Students don't need to see links such as edit or add items in the admin bar
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		public function wp_before_admin_bar_render__removeAdminBarLinksForStudents()
		{

			global $wp_admin_bar;

			$userID = get_current_user_id();

			$isStudent = Studiorum_Utils::usersRoleIs( 'studiorum_student' );

			if( !$isStudent ){
				return;
			}

			$wp_admin_bar->remove_menu( 'comments' );
			$wp_admin_bar->remove_menu( 'new-content' );
			$wp_admin_bar->remove_menu( 'edit' );
			$wp_admin_bar->remove_menu( 'appearance' );

		}/* wp_before_admin_bar_render__removeAdminBarLinksForStudents() */


		/**
		 * Add editor styles so it's easier for students to create content that appears as it will on the front-end.
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		public function init__addEditorStyles()
		{

			$stylesheet = trailingslashit( LECTIO_PLUGIN_URL ) . 'includes/assets/css/front-end-editor-style.css';

			// add_editor_style only works in the admin. *sigh*. So, let's try and manipulate the globa $editor_styles array
			global $editor_styles;
			$editor_styles = (array) $editor_styles;
			$stylesheet    = (array) $stylesheet;
			$editor_styles = array_merge( $editor_styles, $stylesheet );

			// Also add this for the admin
			add_editor_style( $stylesheet );

		}/* init__addEditorStyles() */


		/**
		 * Inline styles (mostly to remove some items from the DFW window)
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		public function wp_enqueue_scripts__inlineStyles()
		{

			$removeUpdateButton = '#wp-fullscreen-save input{ display: none !important; }';

			wp_add_inline_style( 'editor-buttons', $removeUpdateButton );
			wp_add_inline_style( 'editor-buttons-css', $removeUpdateButton );

		}/* wp_enqueue_scripts__inlineStyles() */


		/**
		 * Enqueue some front end styles
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		public function wp_enqueue_scripts__frontEndStyles()
		{

			// First check we're on a page with a valid gForm (set in options)
			if( !Studiorum_Lectio_Utils::isAssignmentEntryPage() && !is_singular( Studiorum_Lectio_Utils::$postTypeSlug ) ){
				return false;
			}

			wp_enqueue_style( 'studiorum-lectio-front-end-styles', trailingslashit( LECTIO_PLUGIN_URL ) . 'includes/assets/css/studiorum-lectio-front-end-styles.css' );

		}/* wp_enqueue_scripts__frontEndStyles() */


		/**
		 * Back-end styles
		 *
		 * @since 0.1
		 *
		 * @param null
		 * @return null
		 */

		public function admin_enqueue_scripts__backEndStyles()
		{

			wp_enqueue_style( 'studiorum-lectio-back-end-styles', trailingslashit( LECTIO_PLUGIN_URL ) . 'includes/admin/assets/css/studiorum-lectio-admin.css' );

		}/* admin_enqueue_scripts__backEndStyles() */


		/**
		 * When a student logs in, let's send them to either a redirect_to url or the home page of this site
		 *
		 * @since 0.1
		 *
		 * @param string $redirect_to URL to redirect to.
		 * @param string $request URL the user is coming from.
		 * @param object $user Logged user's data.
		 * @return string
		 */

		public function login_redirect__studentDoesNotLogInToBackEnd( $redirect_to, $request, $user )
		{


			if( !$user ){
				return $redirect_to;
			}

			if( !isset( $user->roles ) || !is_array( $user->roles ) ){
				return $redirect_to;
			}

			if( !in_array( 'studiorum_student', $user->roles ) ){
				return $redirect_to;
			}

			// OK, we have a user, it's a student, let's see if we have a redirect_to in the URL
			$redirectTo = ( isset( $_GET['redirect_to'] ) && $_GET['redirect_to'] != '' && $_GET['redirect_to'] != admin_url() ) ? $_GET['redirect_to'] : home_url();

			return $redirectTo;

		}/* login_redirect__studentDoesNotLogInToBackEnd() */


		/**
		 * Register ourself as a studiorum addon, so it's available in the main studiorum page
		 *
		 * @since 0.1
		 *
		 * @param array $modules Currently registered modules
		 * @return array $modules modified list of modules
		 */

		public function studiorum_modules__registerAsModule( $modules )
		{

			if( !$modules || !is_array( $modules ) ){
				$modules = array();
			}

			$modules['studiorum-lectio'] = array(
				'id' 				=> 'lectio',
				'plugin_slug'		=> 'studiorum-lectio',
				'title' 			=> __( 'Lectio', 'studiorum' ),
				'icon' 				=> 'clipboard', // dashicons-#
				'excerpt' 			=> __( 'Add the ability for students to submit rich content to your website all from the front-end.', 'studiorum' ),
				'image' 			=> 'http://dummyimage.com/310/162',
				'link' 				=> 'http://code.ubc.ca/studiorum/lectio',
				'content' 			=> __( '<p>By levaraging Gravity Forms (another WordPress plugin), Lectio gives you a way to create an assignment submission form giving your students the capabiity to submit assignments with a rich text editor all from the front-end of your site.</p><p>When a student makes a submission they are taken to a copy of that submission which only they (and you) can see. If you enable the Studiorum User Groups addon, then students in the same group as the one who made the submission can also see and comment on the submission.</p><p>Studiorum also allows you to limit the number of times each student can submit an assignment.</p><p>If you enable the Studiorum Side Comments add-on then you and the student are able to make comments on a paragraph-by-paragraph basis.</p>', 'studiorum' ),
				'content_sidebar' 	=> 'http://dummyimage.com/300x150',
				'date'				=> '2014-08-01'
			);

			return $modules;

		}/* studiorum_modules__registerAsModule() */


		/**
		 * Add the print layout if the studiorum-print-me plugin is active and some hits <permalink>/print/
		 *
		 * @author Richard Tape <@richardtape>
		 * @since 1.0
		 * @param null
		 * @return null
		 */

		public function template_redirect__addPrintLayout()
		{

			global $wp_query;

			// Ensure the studiorum print plugin is up and running
			if( !defined( 'STUDIORUM_PRINT_PLUGIN_DIR' ) ){
				return;
			}

			// if this is not a request for print or a singular lectio submission, then bail
			if( !isset( $wp_query->query_vars['print'] ) || !is_singular( Studiorum_Lectio_Utils::$postTypeSlug ) ){
				return;
			}

			// OK, let's get our data set up about this post
			global $post;
			$postID = $post->ID;

			$allComments = get_comments( array( 'post_id' => $postID, 'type' => '' ) );

			$linearComments = array();

			foreach( $allComments as $key => $commentObject ) {
				if( !$commentObject->comment_type ){
					$linearComments[] = $commentObject;
				}
			}

			$sideComments = CTLT_WP_Side_Comments::getPostCommentData( $postID );

			if( !is_array( $sideComments ) ){
				$sideComments = array();
			}

			$postContent = apply_filters( 'the_content', $post->post_content );

			preg_match_all( "/<p[^>]*>(.*)<\/p>/", $postContent, $contentBreakdown );

			// Also, it will contain any other content that is added in via the_content filter, and we only want
			// <p> tags that have a class of 'commentable-section'
			$onlyCommentableSectionContent = array();

			$justContent = array();

			foreach( $contentBreakdown[0] as $cbKey => $html )
			{

				// if we don't have 'commentable-section' and 'data-section-id' then remove
				if( ( strpos( $html, 'commentable-section' ) === false ) || ( strpos( $html, 'data-section-id' ) === false ) ){
					continue;
				}

				$justContent[$cbKey] = $contentBreakdown[1][$cbKey];

			}

			$contentWithSideComments = array();

			// OK now we have an array of strings (starting at [1]). We need to add the side comments
			foreach( $justContent as $pKey => $pText )
			{

				if( !array_key_exists( $pKey, $sideComments ) ){
					$contentWithSideComments[$pKey] = '<p>' . $pText . '</p>';
					continue;
				}

				// OK, so this paragraph has side comments
				// Let's add a div for the side comments
				$toAdd = '';
				$toAdd .= '<p>' . $pText . '</p>' . '<div class="side-comments-after-p"><ul>';

				// Grab the side comments, then loop over them and add each as a list item
				$sideCommentsForThisP = array_reverse( $sideComments[$pKey] );
				foreach( $sideCommentsForThisP as $sidecommentKey => $sideCommentArray )
				{

					$toAdd .= '<li>';
						$toAdd .= $sideCommentArray['authorName'] . ': ' . $sideCommentArray['comment'];
					$toAdd .= '</li>';

				}

				// Close the 'side-comments-after-p'
				$toAdd .= '</ul></div>';

				$contentWithSideComments[$pKey] = $toAdd;

			}

			$printPageTemplate = apply_filters( 'studiorum_lectio_print_template_path', Studiorum_Utils::locateTemplateInPlugin( LECTIO_PLUGIN_DIR, 'includes/templates/lectio-print-stylesheet.php' ) );

			if( !empty( $printPageTemplate ) ){
				include( $printPageTemplate );
			}

			exit;

		}/* template_redirect__addPrintLayout() */


	}/* class Studiorum_Lectio */

	// Initialize ourselves
	if( class_exists( 'Studiorum_Lectio' ) )
	{

		$studiorumLectio = new Studiorum_Lectio();

		$GLOBALS['studiorum_addons'][] = $studiorumLectio;

	}
