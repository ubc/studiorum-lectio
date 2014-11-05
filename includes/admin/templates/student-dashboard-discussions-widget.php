<?php

	/**
	 * Template for the main part of the student dashboard discussions widget
	 *
	 * @since 0.1
	 * @var array $postData 		- details about each submission post made by this user
	 *
	 */

?>

	<div class="submissions-container">

	<h2 class="<?php echo $class; ?>">
		<?php echo $text; ?>
	</h2>

		<ul>
			<?php foreach( $data as $indiv => $nameAndPosts ) : ?>
				<li>
					<h2><?php echo $nameAndPosts['name']; ?></h2>
					<ul>
						<?php foreach( $nameAndPosts['posts'] as $key => $postInfo ){ include( Studiorum_Utils::locateTemplateInPlugin( LECTIO_PLUGIN_DIR, 'includes/admin/templates/parts/student-dashboard-discussions-widget-post-data.php' ) ); } ?>
					</ul>
				</li>
			<?php endforeach; ?>
		</ul>

	</div><!-- .submissions-container -->