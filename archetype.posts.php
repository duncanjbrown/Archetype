<?php
/**
 * @package archetype
 * @subpackage posts
 */
class Post {

	public $_post;

	/**
	 * @param int $post_id
	 * @throws Exception
	 */
	public function __construct( $post_id, $blog_id = false ) {

		if ( empty( $post_id ) )
			throw new Exception( '$post_id empty' );

		$this->_post = get_post( $post_id );

		if ( ! $this->_post )
			throw new Exception( 'Post not found' );
	}

	/**
	 * @return int Get the ID of the post
	 */
	public function get_id() {
		return $this->_post->ID;
	}

	/**
	 * Get the parent of the post, if any
	 *
	 * @return Post|null
	 */
	public function get_parent() {

		if ( $this->_post->post_parent )
			return new Post( $this->_post->post_parent );

		return null;
	}

	/**
	 * Get the children of the post (if any)
	 * @return StdClass[]
	 */
	public function get_children() {
		return get_children( 'post_parent=' . $this->get_id() );
	}

	/**
	 * Check if the post has a thumbnail
	 *
	 * @return bool
	 */
	public function has_thumbnail() {

		return has_post_thumbnail( $this->get_id() );
	}

	/**
	 * Get the thumbnail HTML for the post
	 *
	 * @param array|string $size
	 * @return string
	 */
	public function get_thumbnail( $size, $attr = '' ) {
		return get_the_post_thumbnail( $this->get_id(), $size, $attr );
	}

	/**
	 * Get the post thumbnail ID
	 * @return mixed int|false
	 */
	public function get_thumbnail_id() {
		return get_post_thumbnail_id( $this->get_id() );
	}

	/**
	 * Get the date the post was created
	 *
	 * @param string $format
	 * @return string
	 */
	public function get_date( $format = 'U' ) {

		return date( $format, strtotime( $this->_post->post_date_gmt ) );
	}

	/**
	 * Set the post date of the post
	 * @param int $time PHP timestamp
	 */
	public function set_date( $time ) {
		$this->_post->post_data = date( 'Y-m-d H:i:s', $time );

		wp_update_post( array( 'ID' => $this->get_id(), 'post_date' => $this->_post->post_data ) );
	}

	/**
	 * Get the local date the post was created
	 *
	 * @param string $format
	 * @return string
	 */
	public function get_local_date( $format = 'U' ) {

		return date( $format, strtotime( $this->_post->post_date ) );
	}

	public function get_meta( $key, $single = false ) {
		return get_post_meta( $this->get_id(), $key, $single );
	}

	public function update_meta( $key, $value ) {
		return update_post_meta( $this->get_id(), $key, $value );
	}

	public function add_meta( $key, $value ) {
		return add_post_meta( $this->get_id(), $key, $value );
	}

	public function delete_meta( $key, $value = null ) {
		return delete_post_meta( $this->get_id(), $key, $value );
	}

	public function get_title() {

		return get_the_title( $this->get_id() );
	}

	public function get_content( $more = 'Read more...' ) {

		if ( ! isset( $this->_content ) ) {
			setup_postdata( $this->_post );

			ob_start();
			the_content( $more );

			$this->_content = ob_get_clean();
			wp_reset_postdata();
		}

		return $this->_content;
	}

	public function get_raw_content() {
		return $this->_post->post_content;
	}

	public function get_author() {

		if ( $this->_post->post_author )
			return User::get( $this->_post->post_author );

		return null;
	}

	public function get_permalink() {
		return get_permalink( $this->get_id() );
	}

	public function get_edit_link() {
		return get_edit_post_link( $this->get_id() );
	}

	public function get_excerpt() {

		if ( ! isset( $this->_excerpt ) ) {
			setup_postdata( $this->_post );

			ob_start();
			the_excerpt();

			$this->_excerpt = ob_get_clean();
			wp_reset_postdata();
		}

		return $this->_excerpt;

	}

	public function get_status() {
		return $this->_post->post_status;
	}

	public function set_status( $status ) {

		$this->_post->post_status = $status;
		wp_update_post( array( 'ID' => $this->get_id(), 'post_status' => $status ) );
	}

}



class Term {

	protected $_term;

	function __construct( $term ) {
		if( !is_object( $term ) )
			throw new Exception( 'Missing term object' );
		$this->_term = $term;
	}

	public function get_name() {
		return $this->_term->name;
	}

	public function get_slug() {
		return $this->_term->slug;
	}

	public function get_edit_link( $text = false ) {
		return edit_term_link( $text, '', '', $this->_term, false );
	}

	public function get_id() {
		return $this->_term->term_id;
	}

	public function get_taxonomy_id() {
		return $this->_term->term_taxonomy_id;
	}

	public function get_taxonomy() {
		return $this->_term->taxonomy;
	}

	public function get_parent() {
		return $this->_term->parent;
	}

	public function get_term() {
		return $this->_term;
	}

	public function get_link() {
		return get_term_link( $this->_term );
	}

	public function get_description() {
		return $this->_term->description;
	}

	public function get_example_post() {

		$query = new WP_Query( array( 
			'numberposts' => 1,
			'orderby' => 'rand', 
			$this->get_taxonomy() => $this->get_slug() 
		) );

		if( $query->posts ) 
			return array_pop( $query->posts );

		return null;
	}

	public function __toString() {
		return $this->get_name();
	}

}
