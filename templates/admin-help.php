<?php
/**
 * Help screen: every topic, with a way in by symptom.
 *
 * @package BanzaiEmbed
 */

use BanzaiEmbed\Help;

defined( 'ABSPATH' ) || exit;

$topics   = Help::topics();
$symptoms = Help::symptoms();
?>
<div class="bzem-page-head">
	<div>
		<h1><?php esc_html_e( 'Help', 'banzaiembed' ); ?></h1>
		<p class="bzem-help-intro"><?php esc_html_e( 'What each part of BanzaiEmbed is for, and when to use it. On an app\'s edit screen, the ? next to a card\'s title opens the same help there.', 'banzaiembed' ); ?></p>
	</div>
</div>
<hr class="wp-header-end">

<div class="bzem-help-page">
	<section class="bzem-card">
		<header class="bzem-card-header">
			<h2><span class="bzem-card-icon dashicons dashicons-sos" aria-hidden="true"></span><?php esc_html_e( 'Fix a problem', 'banzaiembed' ); ?></h2>
		</header>
		<div class="bzem-card-body">
			<ul class="bzem-help-symptoms">
				<?php foreach ( $symptoms as $symptom ) : ?>
					<li>
						<a href="#<?php echo esc_attr( $symptom[1] ); ?>">
							<span><?php echo esc_html( $symptom[0] ); ?></span>
							<span class="bzem-help-symptom-topic">
								<?php echo esc_html( $topics[ $symptom[1] ]['title'] ); ?>
								<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>

	<nav class="bzem-help-toc" aria-label="<?php esc_attr_e( 'Help topics', 'banzaiembed' ); ?>">
		<?php foreach ( $topics as $id => $topic ) : ?>
			<a href="#<?php echo esc_attr( $id ); ?>"><span class="dashicons <?php echo esc_attr( $topic['icon'] ); ?>" aria-hidden="true"></span><?php echo esc_html( $topic['title'] ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php foreach ( $topics as $id => $topic ) : ?>
		<section id="<?php echo esc_attr( $id ); ?>" class="bzem-card bzem-help-section<?php echo $topic['pro'] ? ' bzem-card-pro' : ''; ?>">
			<header class="bzem-card-header">
				<h2><span class="bzem-card-icon dashicons <?php echo esc_attr( $topic['icon'] ); ?>" aria-hidden="true"></span><?php echo esc_html( $topic['title'] ); ?></h2>
				<?php if ( $topic['pro'] ) : ?>
					<span class="bzem-pro-badge"><span class="dashicons dashicons-star-filled" aria-hidden="true"></span><?php esc_html_e( 'Pro', 'banzaiembed' ); ?></span>
				<?php endif; ?>
			</header>
			<div class="bzem-card-body bzem-help-topic">
				<?php Help::render_topic( $id ); ?>
				<?php if ( $topic['pro'] && ! bzem_has_valid_license() ) : ?>
					<p class="bzem-help-upgrade">
						<?php esc_html_e( 'Part of BanzaiEmbed Pro.', 'banzaiembed' ); ?>
						<a href="<?php echo esc_url( bzem_fs()->get_upgrade_url() ); ?>"><?php esc_html_e( 'View plans', 'banzaiembed' ); ?></a>
					</p>
				<?php endif; ?>
				<p class="bzem-help-top"><a href="#wpbody"><?php esc_html_e( 'Back to top', 'banzaiembed' ); ?></a></p>
			</div>
		</section>
	<?php endforeach; ?>
</div>
