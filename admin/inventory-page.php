<?php
/**
 * Страница «Склад»: сколько мантий каждого цвета и размера есть всего.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gr_inventory_page() {
	$stock = gr_get_stock_table();
	$today = current_time( 'Y-m-d' );
	$sizes = gr_sizes();
	?>
	<div class="wrap">
		<h1>Склад мантий</h1>
		<?php gr_admin_notice(); ?>
		<p>Укажите, сколько мантий каждого цвета и размера у вас <strong>всего</strong>.
			Под каждым полем — сколько из них занято бронями сегодня.</p>
		<p class="description">Отдали мантию в химчистку или она испортилась — просто уменьшите число и сохраните.
			Вернулась — увеличьте обратно.</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gr_save_inventory">
			<?php wp_nonce_field( 'gr_save_inventory' ); ?>

			<table class="widefat striped" style="max-width:760px;">
				<thead>
					<tr>
						<th>Цвет</th>
						<?php foreach ( $sizes as $size_label ) : ?>
							<th><?php echo esc_html( $size_label ); ?></th>
						<?php endforeach; ?>
						<th>Всего</th>
					</tr>
				</thead>
				<tbody>
				<?php
				$grand_total = 0;
				foreach ( gr_colors() as $color => $color_label ) :
					$row_total = array_sum( array_intersect_key( $stock[ $color ], gr_color_sizes( $color ) ) );
					$grand_total += $row_total;
					?>
					<tr>
						<td><strong><?php echo esc_html( $color_label ); ?></strong></td>
						<?php
						foreach ( array_keys( $sizes ) as $size ) :
							if ( ! gr_is_offered( $color, $size ) ) :
								?>
								<td><span aria-label="Такого размера нет">—</span><br><small>нет в прокате</small></td>
								<?php
								continue;
							endif;
							$busy = gr_get_peak_usage( $color, $size, $today, $today );
							?>
							<td>
								<input type="number" min="0" max="999" style="width:80px;"
									name="stock[<?php echo esc_attr( $color ); ?>][<?php echo esc_attr( $size ); ?>]"
									value="<?php echo esc_attr( $stock[ $color ][ $size ] ); ?>">
								<br><small>занято сегодня: <?php echo (int) $busy; ?></small>
							</td>
						<?php endforeach; ?>
						<td><strong><?php echo (int) $row_total; ?></strong></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<th colspan="<?php echo count( $sizes ) + 1; ?>">Итого мантий</th>
						<th><?php echo (int) $grand_total; ?></th>
					</tr>
				</tfoot>
			</table>

			<?php submit_button( 'Сохранить склад' ); ?>
		</form>
	</div>
	<?php
}

/** Обработчик формы склада (admin-post.php?action=gr_save_inventory). */
function gr_handle_save_inventory() {
	gr_check_request( 'gr_save_inventory' );

	$raw = isset( $_POST['stock'] ) && is_array( $_POST['stock'] ) ? wp_unslash( $_POST['stock'] ) : array();

	// Сохраняем только известные цвета и размеры.
	foreach ( array_keys( gr_colors() ) as $color ) {
		foreach ( array_keys( gr_color_sizes( $color ) ) as $size ) {
			if ( isset( $raw[ $color ][ $size ] ) ) {
				gr_set_stock( $color, $size, absint( $raw[ $color ][ $size ] ) );
			}
		}
	}

	gr_redirect( 'gr-inventory', array( 'gr_msg' => 'inventory' ) );
}
add_action( 'admin_post_gr_save_inventory', 'gr_handle_save_inventory' );
