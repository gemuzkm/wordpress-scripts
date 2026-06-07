add_filter( 'relevanssi_content_to_index', 'rlv_pdfembedder_content', 10, 2 );
function rlv_pdfembedder_content( $content, $post ) {
    $m = preg_match_all( '/\[pdf-embedder url=["\'](.*?)["\']/', $post->post_content, $matches );
    if ( $m ) {
        global $wpdb;
        $upload_dir = wp_upload_dir();
        foreach ( $matches[1] as $pdf ) {
            $pdf_url     = ltrim( str_replace( $upload_dir['baseurl'], '', urldecode( $pdf ) ), '/' );
            $pdf_content = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = '_relevanssi_pdf_content' AND post_id IN ( SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s )", $pdf_url ) );
            $content    .= $pdf_content;
        }
    }
    return $content;
}