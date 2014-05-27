<?php
/**
 * Represents the view for the administration dashboard.
 *
 * This includes the header, options, and other information that should provide
 * The User Interface to the end user.
 *
 * @package   oeticket_event_importer
 * @author    Jan Beck <mail@jancbeck.com>
 * @license   GPL-2.0+
 * @link      http://jancbeck.com/
 * @copyright 2014 Jan Beck
 */
?>

<div class="tribe_settings wrap">

	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

	<?php if ( !empty( $this->errors ) ) : ?>
		<div class="error">
			<p><strong><?php _e( 'The following errors have occurred:', $this->plugin_slug ); ?></strong></p>
			<ul class="admin-list">
				<?php foreach ( $this->errors as $error ) : ?>
					<li><?php echo $error; ?></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $this->no_events_imported ) : ?>
				<p><?php _e( 'Please note that as a result, no events were successfully imported.', $this->plugin_slug ); ?></p>
			<?php else : ?>
				<p><?php _e( 'Please note that other events have been successfully imported.', $this->plugin_slug ); ?></p>
			<?php endif; ?>
		</div>
	<?php elseif ( $this->success ) : ?>
		<div class="updated">
			<p><?php

				printf(_n('The event has been successfully imported.', 'The %d events have been successfully imported.', $this->imported_total, $this->plugin_slug ), $this->imported_total);

			?></p>
		</div>
	<?php endif; ?>

	<?php if( !empty( $this->errors_images ) ) : ?>
		<div class="error">
			<p><strong><?php _e( 'The following errors have occurred during importing images:', $this->plugin_slug ); ?></strong></p>
			<ul class="admin-list">
				<?php foreach ( $this->errors_images as $error ) : ?>
					<li><?php echo $error; ?></li>
				<?php endforeach; ?>
			</ul>
			<p><?php _e( 'Please note that this does not effect importing of associated events unless noted.', $this->plugin_slug ); ?></p>
		</div>
	<?php endif; ?>

	<div class="tribe-settings-form">

		<form method="post">


			<div>
				<p><label for="oeticket-import-events-by-id"><?php _e( 'Import events by their oeticket URL:', $this->plugin_slug ); ?></label></p>
				<textarea id="oeticket-import-events-by-id" name="oeticket-import-events-by-id" rows="5" cols="130"></textarea>
				<p><span class="description"><?php _e( 'One event URL per line', $this->plugin_slug ); ?></span></p>
			</div>

			<?php wp_nonce_field( 'oeticket-event-import', 'oeticket-confirm-import' ) ?>
			<input id="oeticket-event-import-submit" class="button-primary" type="submit" value="<?php _e( 'Import events', $this->plugin_slug ); ?>">

		<form>
	</div>

</div>
