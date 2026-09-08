<?php
/**
 * Certificate overlay.
 *
 * A variant whose artwork is a blank template (`cert_template`) gets its
 * activity ID and approved hours drawn onto the PDF at render time, so the
 * certificate always states what the registry says. The legacy artwork
 * carries its own text and is left alone.
 *
 * Coordinates are in points on an A4 portrait page and belong to the
 * template artwork in assets/img.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'learndash_certification_content_write_cell_after', 'hcp_mca_certificate_overlay', 10, 2 );

function hcp_mca_certificate_overlay( $pdf, $cert_args ): void {
	$cert_id = isset( $cert_args['cert_post']->ID ) ? (int) $cert_args['cert_post']->ID : 0;
	$variant = $cert_id ? hcp_mca_variant_by( 'cert', $cert_id ) : null;
	if ( null === $variant || empty( $variant['cert_template'] ) || ! is_object( $pdf ) ) {
		return;
	}

	// Coordinates below are points on an A4 portrait page (595 x 842); TCPDF
	// may be working in another unit, so scale by the page width.
	$k = $pdf->getPageWidth() / 595.276;
	$u = fn( float $pt ) => $pt * $k;

	$pdf->SetTextColor( 20, 41, 74 );

	// "Activity ID XXX", centred, between the course title and "on".
	$pdf->SetFont( 'helvetica', 'B', 25 );
	$pdf->SetXY( 0, $u( 300 ) );
	$pdf->Cell( $pdf->getPageWidth(), $u( 34 ), 'Activity ID ' . $variant['activity_id'], 0, 0, 'C' );

	// Approved hours inside the RACGP box: label, number, "hours" per category.
	$columns = [
		[ 'label' => 'Reviewing Performance', 'value' => $variant['hours_rp'], 'x' => 218 ],
		[ 'label' => 'Measuring Outcomes',    'value' => $variant['hours_mo'], 'x' => 298 ],
	];
	foreach ( $columns as $col ) {
		$pdf->SetFont( 'helvetica', 'B', 6.5 );
		$pdf->SetXY( $u( $col['x'] ), $u( 533 ) );
		$pdf->Cell( $u( 78 ), $u( 10 ), $col['label'], 0, 0, 'C' );
		$pdf->SetFont( 'helvetica', '', 26 );
		$pdf->SetXY( $u( $col['x'] ), $u( 545 ) );
		$pdf->Cell( $u( 78 ), $u( 30 ), $col['value'], 0, 0, 'C' );
		$pdf->SetFont( 'helvetica', '', 7 );
		$pdf->SetXY( $u( $col['x'] ), $u( 573 ) );
		$pdf->Cell( $u( 78 ), $u( 10 ), 'hours', 0, 0, 'C' );
	}
}

/**
 * Absolute path of a variant's template artwork inside the plugin, or null.
 */
function hcp_mca_certificate_template_path( array $variant ): ?string {
	if ( empty( $variant['cert_template'] ) ) {
		return null;
	}
	$path = HCP_MCA_PLUGIN_DIR . $variant['cert_template'];
	return file_exists( $path ) ? $path : null;
}
