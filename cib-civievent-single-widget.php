<?php

/*
 * Deliver ONE SINGLE event as a shortcode.
 *
 * @param array $atts The shortcode attributes provided
 * Available attributes include:
 *  - title string The widget title (default automatically fills
 *    with the event title),
 *  - wtheme string The widget theme (default: "standard"),
 *  - divider string The location field delimiter (default comma),
 *  - city bool 1 = display event city,
 *  - state string display event state/province:
 *   	'abbreviate' - abbreviation
 *   	'full' - full name
 *   	'none' (default) - display nothing
 *  - country bool 1 = display event country,
 *  - offset int The number of events to skip (default: 0).
 *  - event_type_id int filter to a single event type.
 *
 * All booleans default to false; any value makes them true.
 *
 * @return string The widget to drop into the post body.
 */
add_shortcode( 'civievent_single_widget', 'civievent_single_widget_shortcode' );
function civievent_single_widget_shortcode( $atts )
{
  // please please do not ask me why this works.
  // I had problems this shortcode was EXECUTED twice, only the LAST one ended up on the page,
  // by outputting time() into log AND page I figured this.
  // most people suggest using the "did_execute" flag, but that did not output anything.
  // so have the source static and outputing it the second time around worked.
  // again, please dont ask me.
  static $did_execute = false;
  static $src = "";
  if($did_execute)
  {
    // Return the widget the second time around.
    error_log("ERROR: cib-civievent-single-widget.php: this is weird, cause I need to do this on othe machines ... ".$src);
    return $src;
  }
  $did_execute = true;

  // instance.
	$widget = new civievent_single_Widget( true );
	$defaults = $widget->_defaultWidgetParams;
  
	// Taking care of those who take things literally, not me I promise.
	if( is_array( $atts ) )
  {
		foreach ( $atts as $k => $v )
    {
			if ( 'false' === $v )
      {
				$atts[ $k ] = false;
			}
		}
	}
  // make upthe defaults
	foreach ( $defaults as $param => $default )
  {
		if ( ! empty( $atts[ $param ] ) )
    {
			$defaults[ $param ] = ( false === $default ) ? true : $atts[ $param ];
		}
	}
	$widgetAtts = array();

  // get the HTML from the output function
  $src = $widget->create_widget( $widgetAtts, $defaults );
  return $src;
}


function civievent_add_dynamic_og_image()
{
  // only add for certain pages, othewise get out
  $theID="";
  if(!empty($_GET['eventID']) && $_GET['eventID']!="")
  {
    if(!is_numeric($_GET['eventID']))
    {
      // naughty people
      error_log( 'CiviCRM API Error: eventID not numeric:'.$_GET['eventID']);
      return 'CiviCRM API Error: eventID not numeric:'.$_GET['eventID'];
    }
    // we know it's ok
    $theID=$_GET['eventID'];

    // get the transient save in the DB
    $event = get_transient( "cib_civievent_single_event_".$theID);

    // the custom value is made up by CRM of "custom_" and the EVENT id.
    $customValue="custom_".$event['customValue'];
    $image_url=$event[$customValue];

    // get rid of stuff in the summary so we can POST it
    $summary=str_replace("\n"," ",$event['summary']);
    $summary=str_replace("\r"," ",$summary);
    $summary=str_replace("\m"," ",$summary);

    // send the meta stuff to the header
    echo "<meta property='og:image' content='".$image_url."' />\n";
    echo "<meta property='og:description' content='".$summary."'>\n";
    echo "<meta name='description' content='".$summary."'>\n";
  }
}

// single widget class
class civievent_single_Widget extends civievent_Widget
{
	/**
	 * Default parameter values
	 * @var array $_defaultWidgetParams Default parameters
	 */
	public $_defaultWidgetParams = array(
		'title' => '',
		'wtheme' => 'standard',
		'alllink' => false,
		'city' => false,
		'state' => 'none',
		'country' => false,
		'divider' => ', ',
		'offset' => 0,
		'event_type_id' => '',
    'metatags' => 'yes',
	);

	/**
	 * Construct the basic widget object.
	 * @param bool $shortcode Whether this is actually a shortcode, not a widget.
	 */
	public function __construct( $shortcode = false )
  {
		WP_Widget::__construct(
      // Base ID
			'civievent-single-widget',
      // Name
			__( 'Single CiviEvent Widget', 'civievent-widget' ),
      // Args.
			array( 'description' => __( 'displays a single CiviCRM event', 'civievent-widget' ) ) 
		);

		if($shortcode)
    {
			$this->_isShortcode = true;
		}
		$this->commonConstruct();
	}

	/**
	 * Create the widget
	 * @param array $args Widget arguments.
	 * @param array $instance Widget instance.
	 */
	public function create_widget( $args, $instance )
  {
    // no civicrm
		if ( ! function_exists( 'civicrm_initialize' ) )
    {
      return;
    }
    
    // civi too old
		if ( version_compare( $this->_civiversion, '4.3.alpha1' ) < 0 )
    {
      return;
    }

    // only react if the eventID is set
    if(!empty($_GET['eventID']) && $_GET['eventID']!="")
    {
      // SINGLE WIDGET EVENT ID *IS* SET
      if(!is_numeric($_GET['eventID']))
      {
        // just fail
        error_log( 'CiviCRM API Error: eventID not numeric:'.$_GET['eventID']);
        return 'CiviCRM API Error: eventID not numeric:'.$_GET['eventID'];
      }
      try {
        $event_args = array(
          'id' => $_GET['eventID'],
        );
        $event = civicrm_api3( 'Event', 'getsingle', $event_args );
      }
      catch ( CiviCRM_API3_Exception $e )
      {
        // cant do anythhing
        error_log( 'CiviCRM API Error: ' . $e->getMessage() );
        return 'CiviCRM API Error: ' . $e->getMessage();
      }
      if($instance['metatags']=='yes')
      {
        if(!isset( $event['title'] ) )
        {
          // cant do anything
          error_log("SINGLE WIDGET EVENT (metatags) - We are so fucked, no title ");
          return "<h2>the parameters provided are incorrect, possibly the event id does not exist</h2>";
        }
        // need to set up the call to add to wphead.
        add_action('wp_head', 'civievent_add_dynamic_og_image');
      }
    }
    else
    {
      // SINGLE WIDGET EVENT ID NOT SET
      try
      {
        $event_args = array(
          'sequential' => 1,
          'is_active' => 1,
          'is_public' => 1,
          'is_template' => 0,
          'options' => array(
            'limit' => 1,
            'sort' => 'start_date ASC',
            'offset' => CRM_Utils_Array::value( 'offset', $instance, 0 ),
          ),
          'start_date' => array( '>=' => date( 'Y-m-d' ) ),
        );
        if ( ! empty( $instance['event_type_id'] ) )
        {
          $event_args['event_type_id'] = intval( $instance['event_type_id'] );
        }
        $event = civicrm_api3( 'Event', 'getsingle', $event_args );
      }
      catch ( CiviCRM_API3_Exception $e )
      {
        // cant do anythhing
        error_log( 'CiviCRM API Error: ' . $e->getMessage() );
        return 'CiviCRM API Error: ' . $e->getMessage();
      }
    }
    
    // no title no go
    if(!isset( $event['title'] ) )
    {
      // cant do anything, really
      error_log("SINGLE WIDGET EVENT - We are so fucked, no title ");
      return "<h2>the parameters provided are incorrect, possibly the event id does not exist</h2>";
    }

    // get the label for the image
    try
    {
      $customImage = civicrm_api3('custom_field', 'get', array(
        'label' => "cibapp_Image_Link",
      ));
    }
    catch (CiviCRM_API3_Exception $e) {
      // We need to stop here, the field is not set up in the DB
      error_log("SINGLE WIDGET EVENT - We are so fucked, no data returned from query: ".$e->getMessage());
      return "<h2>the parameters provided are incorrect, field 'label' => 'cibapp_Image_Link' not correctly setup in the data base, please fix!</h2>";
    }

    // this is awkward I have to say, there should be a better way to do this
    // CIVICRM uses custom PLUS the id to store the custom value, cant we just use the label?
    $customValue="custom_".$customImage['id'];

    // hand it over to event, too
    // this is used in the functions.php file to ADD to the HEAD
    $event['customValue']=$customImage['id'];
    
    // set the transient so other functions can add it to the head.
    set_transient( "cib_civievent_single_event_".$_GET['eventID'] , $event , 5 );
    
    // this is the image URL
    $daImage="";

    // now check whether the image is set.
    if(!empty($event[$customValue]) && $event[$customValue]!="")
    {
      // so the image is set, display it.
      $daImage="<img src='".$event[$customValue]."' />";
    }
    
    // get the stuff for the links
    $infoLink = CRM_Utils_System::url( 'civicrm/event/info', "reset=1&id={$event['id']}" );
    
    // display title
    if(empty($instance['title']))
    {
      // SHORTCODE
      $title = apply_filters( 'widget_title', $event['title'] );
      $title = "<a href='".$infoLink."'>" . apply_filters( 'widget_title', $event['title'] ) . '</a>';
      $content = '';
    }
    else
    {
      // INSTANCE
      $title = apply_filters( 'widget_title', $instance['title'] );
      $content = "<div class='civievent-widget-single-title'><a href='".$infoLink."'>{$event['title']}</a></div>";
    }

    // summary
    if( ! empty( $event['summary'] ) )
    {
      $content .= "<div class='civievent-widget-single-summary'>{$event['summary']}</div>";
    }
    
    // append the image
    if(!empty($daImage) && $daImage!="" )
    {
      $content .= "<div class='civievent-widget-spacer'>&nbsp;</div>";
      $content .= "<div class='civievent-widget-single-image'>".$daImage."</div>";
    }

    // $content .= $this->dateFix( $event, 'civievent-widget-single' );
    // $content .= self::locFix( $event, $event['id'], $instance, 'civievent-widget-single' );
    // $content .= self::regFix( $event, $event['id'], 'civievent-widget-single' );
    
    if( ! empty( $event['description'] ) )
    {
      $content .= "<div class='civievent-widget-spacer'>&nbsp;</div>";
      $content .= "<div class='civievent-widget-single-summary'>{$event['description']}</div>";
    }

    // make up the URL of THIS page
    global $civicrm_paths;
    $URL= $civicrm_paths['wp.frontend.base']['url']."/events/event-single/eventID=".$event['id'];

    // can only display the buttons if online rego is active
    if( isset($event['is_online_registration']) && $event['is_online_registration']==1 )
    {
      $content .= "<div class='civievent-widget-spacer'>&nbsp;</div>";
      $content .= "<div class='civievent-widget-button-section'>";
      $content .= " <a href='".$civicrm_paths['wp.frontend.base']['url']."/civicrm/event/register/?id=1&amp;reset=1' title='Register Now' class='button btn'>Register Now</a>";
      $content .= " <a href='".$civicrm_paths['wp.frontend.base']['url']."/events/' title='See All Events' class='button btn'>See all events</a>";
      $content .= "</div>";
      $content .= "<div class='civievent-widget-spacer'>&nbsp;</div>";
    }

    // get rid of stuff in the summary so we can POST it
    $TXT=str_replace("\n"," ",$event['summary']);
    $TXT=str_replace("\r"," ",$TXT);
    $TXT=str_replace("\m"," ",$TXT);

    // ask for help at the bottom 
    $content .= "<div class='civievent-widget-spacer'>&nbsp;</div>";
    $content .= "<div class='civievent-widget-spacer'>&nbsp;</div>";
    $content .= "<div class='civievent-widget-social' role='alert'>";
    $content .= " <h3>Help spread the word</h3>";
    $content .= "  <p>Please help us and let your friends, colleagues and followers know about <strong><a href='events/event-single/?eventID=".$event['title']."'>".$event['title']."</a></strong></p>";
    $content .= "  <div class='civievent-widget-button-section'>";
    $content .= '   <button onclick="window.open(\'https://X.com/intent/tweet?url='.$URL.'&amp;text='.$TXT.'\',\'_blank\')" type="button" class="button btn btn-default" id="crm-tw" title="Share">Twitter</button>';
    $content .= '   <button onclick="window.open(\'https://facebook.com/sharer/sharer.php?u='.$URL.'\',\'_blank\')" type="button" class="button btn btn-default" role="button" id="crm-fb" title="Share">Facebook</button>';
    $content .= '   <button onclick="window.open(\'https://www.linkedin.com/shareArticle?mini=true&amp;url='.$URL.'&title='.$TXT.'\',\'_blank\')" type="button" rel="noopener" class="button btn btn-default" id="crm-li" title="Share">LinkedIn</button>';
    $content .= '   <button onclick="window.open(\'mailto:?subject='.$event['title'].'&amp;body='.$TXT.'%0A'.$URL.'\',\'_self\')" type="button" rel="noopener" class="button btn btn-default" id="crm-email" title="Share">Email</button>';
    $content .= "  <p>You can also share the below link in an email or on your website (right click on link, then use copy link):<br/>";
    $content .= "  <a href='".$URL."'>".$URL."</a><br/>";
    $content .= "</div>";

    // need to get this going.
    // It's a FIX ME
    if($instance['alllink'])
    {
      $viewall = CRM_Utils_System::url( 'civicrm/event/ical', 'reset=1&list=1&html=1' );
      $content .= "<div class='civievent-widget-single-viewall'><a href='".$viewall."'>" . ts( 'View all' ) . '</a></div>';
    }

    // classes
    $classes = array();
		$classes[] = ( strlen( $instance['wtheme'] ) ) ? "civievent-widget-single-{$instance['wtheme']}" : 'civievent-widget-single-custom';
		foreach ( $classes as &$class )
    {
			$class = sanitize_html_class( $class );
		}
		$classes = implode( ' ', $classes );

		wp_enqueue_style( 'civievent-widget-Stylesheet' );
		if ( $this->_isShortcode )
    {
      // SHORTCODE
			$content = "<h2 class='title civievent-single-widget-title'>$title</h2>$content";
			return "<div class='civievent-widget $classes'>$content</div>";
		}
    else
    {
      // INSTANCE
			echo $args['before_widget'];
			echo $args['before_title'] . $title . $args['after_title'];
			echo "<div class='".$classes."'>$content</div>";
			echo $args['after_widget'];
		}
	}

	/**
	 * Widget config form.
	 *
	 * @param array $instance The widget instance.
	 */
	public function form( $instance )
  {
		if ( ! function_exists( 'civicrm_initialize' ) )
    {
      ?>
			<h3><?php _e( 'You must enable and install CiviCRM to use this plugin.', 'civievent-widget' ); ?></h3>
      <?php
			return;
		}
    elseif ( version_compare( $this->_civiversion, '4.3.alpha1' ) < 0 )
    {
      ?>
			<h3><?php print __( 'You must enable and install CiviCRM 4.3 or higher to use this plugin.	You are currently running CiviCRM ', 'civievent-widget' ) . $this->_civiversion; ?></h3>
			<?php
			return;
		}
    elseif ( strlen( $this->_civiBasePage ) < 1 )
    {
			$adminUrl = CRM_Utils_System::url( 'civicrm/admin/setting/uf', 'reset=1' );
			?>
      <div class="civievent-widget-nobasepage">
    		<h3><?php _e( 'No Base Page Set', 'civievent-widget' ); ?></h3>
  	  	<?php
        print '<p>' . __( 'You do not have a WordPress Base Page set in your CiviCRM settings.  This can cause the CiviEvent Widget to display inconsistent links.', 'civievent-widget' );
    		print '<a href=' . $adminUrl . '>' . __( 'Please set this', 'civievent-widget' ) . '</a> ' . __( 'before using the widget.', 'civievent-widget' ) . '</p>';
  			?>
			</div>
      <?php
		}

		// Outputs the options form on admin.
		foreach ( $this->_defaultWidgetParams as $param => $val )
    {
			if ( false === $val )
      {
				$$param = isset( $instance[ $param ] ) ? (bool) $instance[ $param ] : false;
			}
      else
      {
				$$param = isset( $instance[ $param ] ) ? $instance[ $param ] : $val;
			}
		}

		?>
		<p>
		  <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'civievent-widget' ); ?></label>
  		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
	  	<?php _e( 'Enter the title for the widget.	You may leave this blank to set the event\'s title as the widget title', 'civievent-widget' ); ?>
		</p>
		<p>
		  <label for="<?php echo $this->get_field_id( 'wtheme' ); ?>"><?php _e( 'Widget theme:', 'civievent-widget' ); ?></label>
  		<input class="widefat" id="<?php echo $this->get_field_id( 'wtheme' ); ?>" name="<?php echo $this->get_field_name( 'wtheme' ); ?>" type="text" value="<?php echo esc_attr( $wtheme ); ?>" />
  		<?php _e( 'Enter the theme for the widget.	The standard option is "standard", or you can enter your own value, which will be added to the widget class name.', 'civievent-widget' ); ?>
		</p>
		<p>
	  	<label for="<?php echo $this->get_field_id( 'offset' ); ?>"><?php _e( 'Offset:', 'civievent-widget' ); ?></label>
  		<input class="widefat" id="<?php echo $this->get_field_id( 'offset' ); ?>" name="<?php echo $this->get_field_name( 'offset' ); ?>" type="text" value="<?php echo esc_attr( $offset ); ?>" />
  	  <?php _e( 'By default, the widget will show the first upcoming event starting today or in the future.  Enter an offset to skip one or more events: for example, an offset of 1 will skip the first event and display the second.', 'civievent-widget' ); ?>
		</p>
		<p>
		  <label for="<?php echo $this->get_field_id( 'event_type_id' ); ?>"><?php _e( 'Event type:', 'civievent-widget' ); ?></label>
		  <?php echo self::event_type_select( $this->get_field_name( 'event_type_id' ), $this->get_field_id( 'event_type_id' ), $event_type_id ); ?>
		</p>
		<p>
      <input type="checkbox" <?php checked( $city ); ?> name="<?php echo $this->get_field_name( 'city' ); ?>" id="<?php echo $this->get_field_id( 'city' ); ?>" class="checkbox">
		  <label for="<?php echo $this->get_field_id( 'city' ); ?>"><?php _e( 'Display city?', 'civievent-widget' ); ?></label>
		</p>
		<p>
		  <label for="<?php echo $this->get_field_id( 'state' ); ?>"><?php _e( 'Display state/province?', 'civievent-widget' ); ?></label>
			<select name="<?php echo $this->get_field_name( 'state' ); ?>" id="<?php echo $this->get_field_id( 'state' ); ?>">
				<option value="none" <?php selected( $state, 'none' ); ?>><?php _e( 'Hidden', 'civievent-widget' ); ?></option>
				<option value="abbreviate" <?php selected( $state, 'abbreviate' ); ?>><?php _e( 'Abbreviations', 'civievent-widget' ); ?></option>
				<option value="full" <?php selected( $state, 'full' ); ?>><?php _e( 'Full names', 'civievent-widget' ); ?></option>
			</select>
		</p>
		<p>
      <input type="checkbox" <?php checked( $country ); ?> name="<?php echo $this->get_field_name( 'country' ); ?>" id="<?php echo $this->get_field_id( 'country' ); ?>" class="checkbox">
		  <label for="<?php echo $this->get_field_id( 'country' ); ?>"><?php _e( 'Display country?', 'civievent-widget' ); ?></label>
		</p>
		<p>
		  <label for="<?php echo $this->get_field_id( 'divider' ); ?>"><?php _e( 'City, state, country divider:', 'civievent-widget' ); ?></label>
		  <input class="widefat" id="<?php echo $this->get_field_id( 'divider' ); ?>" name="<?php echo $this->get_field_name( 'divider' ); ?>" type="text" value="<?php echo esc_attr( $divider ); ?>" />
		  <?php _e( 'Enter the character(s) that should separate the city, state/province, and/or country when displayed.', 'civievent-widget' ); ?>
		</p>
		<p>
      <input type="checkbox" <?php checked( $alllink ); ?> name="<?php echo $this->get_field_name( 'alllink' ); ?>" id="<?php echo $this->get_field_id( 'alllink' ); ?>" class="checkbox">
		  <label for="<?php echo $this->get_field_id( 'alllink' ); ?>"><?php _e( 'Display "view all"?', 'civievent-widget' ); ?></label>
		</p>
		<?php
	}
}
