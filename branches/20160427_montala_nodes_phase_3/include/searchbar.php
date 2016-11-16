<?php

if ($simple_search_reset_after_search)
	{
	$restypes="";
	$search="";
	$quicksearch="";
	$starsearch="";
	}
else 
	{
	# pull values from cookies if necessary, for non-search pages where this info hasn't been submitted
	if (!isset($restypes)) {$restypes=@$_COOKIE["restypes"];}
	if (!isset($search) || ((strpos($search,"!")!==false))) {$quicksearch=(isset($_COOKIE["search"])?$_COOKIE["search"]:"");} else {$quicksearch=$search;}
	}

include_once("search_functions.php");
include_once("render_functions.php");

if(!isset($internal_share_access))
	{
	// Set a flag for logged in users if $external_share_view_as_internal is set and logged on user is accessing an external share
	$internal_share_access = (isset($k) && $k!="" && $external_share_view_as_internal && isset($is_authenticated) && $is_authenticated);
	}

# Load the basic search fields, so we know which to strip from the search string
$fields=get_simple_search_fields();
$simple_fields=array();
for ($n=0;$n<count($fields);$n++)
	{
	$simple_fields[]=$fields[$n]["name"];
	}
# Also strip date related fields.
$simple_fields[]="year";$simple_fields[]="month";$simple_fields[]="day";
hook("simplesearch_stripsimplefields");

# Check for fields with the same short name and add to an array used for deduplication.
$f=array();
$duplicate_fields=array();
for ($n=0;$n<count($fields);$n++)
	{
	if (in_array($fields[$n]["name"],$f)) {$duplicate_fields[]=$fields[$n]["name"];}
	$f[]=$fields[$n]["name"];
	}
			
# Process all keywords, putting set fieldname/value pairs into an associative array ready for setting later.
# Also build a quicksearch string.

$quicksearch    = refine_searchstring($quicksearch);
$keywords       = split_keywords($quicksearch,false,false,false,false,true);

$set_fields     = array();
$simple         = array();
$searched_nodes = array();

for ($n=0;$n<count($keywords);$n++)
	{
	if (trim($keywords[$n])!="")
		{
		if (strpos($keywords[$n],":")!==false && substr($keywords[$n],0,11)!="!properties")
			{
			$s=explode(":",$keywords[$n]);
			if (isset($set_fields[$s[0]])){$set_fields[$s[0]].=" ".$s[1];}
			else {$set_fields[$s[0]]=$s[1];}
			if (!in_array($s[0],$simple_fields)) {$simple[]=trim($keywords[$n]);}
			}
        // Nodes search
        else if(strpos($keywords[$n], NODE_TOKEN_PREFIX) !== false)
            {
            $nodes = resolve_nodes_from_string($keywords[$n]);

            foreach($nodes as $node)
                {
                $searched_nodes[] = $node;
                }

            $searched_nodes = array_unique($searched_nodes);

            foreach($searched_nodes as $searched_node)
                {
                $node = array();

                if(!get_node($searched_node, $node))
                    {
                    continue;
                    }

                $field_index = array_search($node['resource_type_field'], array_column($fields, 'ref'));

                if(false === $field_index)
                    {
                    $quicksearch = str_replace(NODE_TOKEN_PREFIX . $searched_node,
                        rebuild_specific_field_search_from_node($node),
                        $quicksearch);

                    continue;
                    }

                $searched_field = $fields[$field_index];

                // We already have a field on search bar so remove this keyword from search box
                if(true == $searched_field['simple_search'])
                    {
                    $quicksearch = str_replace(NODE_TOKEN_PREFIX . $searched_node, '', $quicksearch);
                    }
                }

            $initial_tags = explode(',', $quicksearch);
            }
		else
			{
			# Plain text (non field) search.
			$simple[]=trim($keywords[$n]);
			}
		}
	}

# Set the text search box to the stripped value.
$quicksearch=join(" ",trim_array($simple));
$quicksearch=str_replace(",-"," -",$quicksearch);

# Set the predefined date fields
$found_year="";if (isset($set_fields["year"])) {$found_year=$set_fields["year"];}
$found_month="";if (isset($set_fields["month"])) {$found_month=$set_fields["month"];}
$found_day="";if (isset($set_fields["day"])) {$found_day=$set_fields["day"];}


if ($display_user_rating_stars && $star_search){ ?>
	<?php if (!hook("replacesearchbarstarjs")){?>
	<script type="text/javascript">

	function StarSearchRatingDisplay(rating,hiclass)
		{
		for (var n=1;n<=5;n++)
			{
			jQuery('#RatingStar-'+n).removeClass('StarEmpty');
			jQuery('#RatingStar-'+n).removeClass('StarCurrent');
			jQuery('#RatingStar-'+n).removeClass('StarSelect');
			if (n<=rating)
				{
				jQuery('#RatingStar-'+n).addClass(hiclass);
				}
			else
				{
				jQuery('#RatingStar-'+n).addClass('StarEmpty');
				}
			}
		}	

	</script>
	<?php } // end hook replacesearchbarstarjs ?>
<?php } ?>

<div id="SearchBox" <?php
    if(isset($slimheader) && $slimheader && isset($slimheader_fixed_position) && $slimheader_fixed_position)
        {
        ?> class="SlimHeaderFixedPosition"<?php
        }
?>>

<?php hook("searchbarbeforeboxpanel"); ?>

<?php if (checkperm("s") && (!isset($k) || $k=="" || $internal_share_access)) { ?>
<div id="SearchBoxPanel">

<?php hook("searchbartoptoolbar"); ?>

<div class="SearchSpace" <?php if (!$basic_simple_search){?>id="searchspace"<?php } ?>>

<?php if (!hook("searchbarreplace")) { ?>

  <?php if (!hook("replacesimplesearchheader")){?><h2><?php echo $lang["simplesearch"]?></h2><?php } ?>

	<label for="ssearchbox"><?php echo text("searchpanel")?></label>
	
	<form id="simple_search_form" method="post" action="<?php echo $baseurl?>/pages/search.php" onSubmit="return CentralSpacePost(this,true);">
    <?php
    if(!hook("replacesearchbox"))
        {
        ?>
        <div class="ui-widget">
        <input id="ssearchbox" <?php if ($hide_main_simple_search){?>type="hidden"<?php } ?> name="search" type="text" class="SearchWidth" value="<?php echo htmlspecialchars(stripslashes(@$quicksearch))?>">
        </div>
        <script>
        <?php
        $autocomplete_src = '';
        if($autocomplete_search)
            {
            $autocomplete_src = "{$baseurl}/pages/ajax/autocomplete_search.php";
            }

        if($simple_search_pills_view)
            {
            // $initial_tags is used for reloading search bar so that the tags will remain the same otherwise separate tags can become one big tag
            $initial_tags = (isset($initial_tags) ? $initial_tags : array());
            ?>
            jQuery('#ssearchbox').tagEditor(
                {
                'initialTags': <?php echo json_encode($initial_tags); ?>,
                'delimiter': '<?php echo TAG_EDITOR_DELIMITER; ?>',
                'autocomplete': {
                    'source': '<?php echo $autocomplete_src; ?>',
                },
                onChange: function(field, editor, tags)
                    {
                    jQuery(document).keyup(function(event)
                        {
                        if(event.key == 'Enter' && event.which === 13)
                            {
                            document.getElementById('simple_search_form').submit();
                            }
                        });
                    }
                });

            // Decide when to add tags:
            // if space addTag
            // if "word" then addTag
            // don't do anything if open " but not closed
            jQuery('ul.tag-editor').keyup(function(e)
                {
                var key          = e.keyCode || e.which;
                var add_tag_flag = false;

                // Get new tag value which is not yet finished/ rendered as a pill
                var existing_tags = jQuery('#ssearchbox').tagEditor('getTags')[0].tags;
                var all_tags      = jQuery('.tag-editor-tag:not(.deleted)', this).map(function(i, e)
                                        {
                                        var val = jQuery.trim(jQuery(this).hasClass('active') ? jQuery(this).find('input').val() : jQuery(e).text());

                                        if(val)
                                            {
                                            return val;
                                            }
                                        }).get();
                var new_tag       = (array_diff(existing_tags, all_tags)[0] || '');

                // Find how many double quotes we have in our tag
                // 1 => spaces are allowed
                // 2 => add tag
                var double_quotes_occurences = (new_tag.match(/"/g) || []).length;

                // 32 is keyCode for " " (spacebar)
                if(key == 32 && double_quotes_occurences == 0)
                    {
                    add_tag_flag = true;
                    }
                // 50 is keyCode for " (double quotes)
                else if(key == 50 && double_quotes_occurences == 2)
                    {
                    add_tag_flag = true;
                    }

                if(add_tag_flag)
                    {
                    jQuery('#ssearchbox').tagEditor('addTag', new_tag);
                    }

                return;
                });
            <?php
            }
        else
            {
            ?>
            jQuery(document).ready(function () {
                jQuery('#ssearchbox').autocomplete({source: "<?php echo $autocomplete_src; ?>"});
            });
            <?php
            }
	        ?>
        </script>
        <?php
        }

if (!$basic_simple_search)
	{
	# Load resource types.
	$types=get_resource_types();
	
	# More than 5 types? Always display the 'select all' option.
	if (count($types)>5) {$searchbar_selectall=true;}
	
	?>
	<input type="hidden" name="resetrestypes" value="yes">
	<div id="searchbarrt" <?php hook("searchbarrtdiv");?>>
	<?php if ($searchbar_selectall) { ?>
	<script type="text/javascript">	
	function resetTickAll(){
		var checkcount=0;
		// set tickall to false, then check if it should be set to true.
		jQuery('#rttickallres').prop('checked',false);
		var tickboxes=jQuery('#simple_search_form .tickbox');
			jQuery(tickboxes).each(function (elem) {
                if( tickboxes[elem].checked){checkcount=checkcount+1;}
            });
		if (checkcount==tickboxes.length){jQuery('#rttickallres').prop('checked',true);}	
	}
	function resetTickAllColl(){
		var checkcount=0;
		// set tickall to false, then check if it should be set to true.
		jQuery('#rttickallcoll').prop('checked',false);
		var tickboxes=jQuery('#simple_search_form .tickboxcoll');
			jQuery(tickboxes).each(function (elem) {
				if( tickboxes[elem].checked){checkcount=checkcount+1;}
			});
		if (checkcount==tickboxes.length){jQuery('#rttickallcoll').prop('checked',true);}	
	}
	</script>
	<div class="tick"><input type='checkbox' id='rttickallres' name='rttickallres' checked onclick='jQuery("#simple_search_form .tickbox").each (function(index,Element) {jQuery(Element).prop("checked",(jQuery("#rttickallres").prop("checked")));}); HideInapplicableSimpleSearchFields(true); '/>&nbsp;<?php echo $lang['allresourcessearchbar']?></div>
	<?php }?>
	<?php
	$rt=explode(",",@$restypes);
	$clear_function="";
	for ($n=0;$n<count($types);$n++)
		{
			if(in_array($types[$n]['ref'], $hide_resource_types)) { continue; }
		?>
		<?php if (in_array($types[$n]["ref"],$separate_resource_types_in_searchbar)) { ?><div class="spacer"></div><?php } ?><div class="tick<?php if ($searchbar_selectall && (!in_array($types[$n]["ref"],$separate_resource_types_in_searchbar)) ){ ?> tickindent<?php } ?>"><input class="tickbox<?php if (in_array($types[$n]["ref"],$separate_resource_types_in_searchbar)) echo "sep"; ?>" id="TickBox<?php echo $types[$n]["ref"]?>" type="checkbox" name="resource<?php echo $types[$n]["ref"]?>" value="yes" <?php if (((count($rt)==1) && ($rt[0]=="")) || ($restypes=="Global") || (in_array($types[$n]["ref"],$rt))) {?>checked="checked"<?php } ?> onClick="HideInapplicableSimpleSearchFields(true);<?php if ($searchbar_selectall && (!in_array($types[$n]["ref"],$separate_resource_types_in_searchbar))){?>resetTickAll();<?php } ?>"/><label for="TickBox<?php echo $types[$n]["ref"]?>">&nbsp;<?php echo htmlspecialchars($types[$n]["name"]) ?></label></div><?php	
		$clear_function.="jQuery('#TickBox" . $types[$n]["ref"] . "').prop('checked',true);";
		if ($searchbar_selectall && (!in_array($types[$n]["ref"],$separate_resource_types_in_searchbar))) {$clear_function.="resetTickAll();";}
		}
		?><div class="spacer"></div>
		<?php if ($searchbar_selectall && ($search_includes_user_collections || $search_includes_public_collections || $search_includes_themes)) { ?>
		<div class="tick"><input type='checkbox' id='rttickallcoll' name='rttickallcoll' checked onclick='jQuery("#simple_search_form .tickboxcoll").each (function(index,Element) {jQuery(Element).prop("checked",(jQuery("#rttickallcoll").prop("checked")));}); HideInapplicableSimpleSearchFields(true); '/>&nbsp;<?php echo $lang['allcollectionssearchbar']?></div>
		<?php }?>
		<?php if ($clear_button_unchecks_collections){$colcheck="false";}else {$colcheck="true";}
		if ($search_includes_user_collections) 
		    { ?>
		    <div class="tick <?php if ($searchbar_selectall){ ?> tickindent <?php } ?>"><input class="tickboxcoll" id="TickBoxMyCol" type="checkbox" name="resourcemycol" value="yes" <?php if (((count($rt)==1) && ($rt[0]=="")) || (in_array("mycol",$rt))) {?>checked="checked"<?php } ?> onClick="HideInapplicableSimpleSearchFields(true);<?php if ($searchbar_selectall){?>resetTickAllColl();<?php } ?>"/><label for="TickBoxMyCol">&nbsp;<?php echo $lang["mycollections"]?></label></div><?php	
		    $clear_function.="jQuery('#TickBoxMyCol').prop('checked'," . $colcheck . ");";
		    if ($searchbar_selectall) {$clear_function.="resetTickAllColl();";}
		    }
	    if ($search_includes_public_collections) 
	        { ?>
	        <div class="tick <?php if ($searchbar_selectall){ ?> tickindent <?php } ?>"><input class="tickboxcoll" id="TickBoxPubCol" type="checkbox" name="resourcepubcol" value="yes" <?php if (((count($rt)==1) && ($rt[0]=="")) || (in_array("pubcol",$rt))) {?>checked="checked"<?php } ?> onClick="HideInapplicableSimpleSearchFields(true);<?php if ($searchbar_selectall){?>resetTickAllColl();<?php } ?>"/><label for="TickBoxPubCol">&nbsp;<?php echo $lang["findpubliccollection"]?></label></div><?php	
	        $clear_function.="jQuery('#TickBoxPubCol').prop('checked'," . $colcheck . ");";
	        if ($searchbar_selectall) {$clear_function.="resetTickAllColl();";}
	        }
	    if ($search_includes_themes) 
	        { ?>
	        <div class="tick <?php if ($searchbar_selectall){ ?> tickindent <?php } ?>"><input class="tickboxcoll" id="TickBoxThemes" type="checkbox" name="resourcethemes" value="yes" <?php if (((count($rt)==1) && ($rt[0]=="")) || (in_array("themes",$rt))) {?>checked="checked"<?php } ?> onClick="HideInapplicableSimpleSearchFields(true);<?php if ($searchbar_selectall){?>resetTickAllColl();<?php } ?>"/><label for="TickBoxThemes">&nbsp;<?php echo $lang["findcollectionthemes"]?></label></div><?php	
	        $clear_function.="jQuery('#TickBoxThemes').prop('checked'," . $colcheck . ");";
	        if ($searchbar_selectall) {$clear_function.="resetTickAllColl();";}
	        }
	   

	}

    if($searchbar_selectall)
        {
        ?>
        <script type="text/javascript">resetTickAll();resetTickAllColl();</script>
        <?php
        }

    if(!$basic_simple_search)
        {
        ?>
        </div>
        <?php
        hook('after_simple_search_resource_types');
        }

	hook("searchfiltertop");

    $searchbuttons="<div class=\"SearchItem\" id=\"simplesearchbuttons\">";
	
	$cleardate="";
	if ($simple_search_date){$cleardate.=" document.getElementById('basicyear').value='';document.getElementById('basicmonth').value='';" ;}
        if ($searchbyday && $simple_search_date) { $cleardate.="document.getElementById('basicday').value='';"; }

	if(!$basic_simple_search)
        {
        $searchbuttons .= "<input name=\"Clear\" id=\"clearbutton\" class=\"searchbutton\" type=\"button\" value=\"&nbsp;&nbsp;".$lang['clearbutton']."&nbsp;&nbsp;\" onClick=\"";

        if($simple_search_pills_view)
        	{
    		$searchbuttons .= "removeSearchTagInputPills(jQuery('#ssearchbox'));";
    		}

		$searchbuttons .= $cleardate;

        if($display_user_rating_stars && $star_search)
            {
            $searchbuttons .= "StarSearchRatingDisplay(0,'StarCurrent');document.getElementById('starsearch').value='';window['StarSearchRatingDone']=true;";
            }

        if($resourceid_simple_search)
            {
            $searchbuttons .= " document.getElementById('searchresourceid').value='';";
            }

        $searchbuttons .= "ResetTicks();\"/>";
        }
    else
        {
        $searchbuttons .= '<input name="Clear" id="clearbutton" class="searchbutton" type="button" value="&nbsp;&nbsp;' . $lang['clearbutton'] . '&nbsp;&nbsp;" onClick="removeSearchTagInputPills(jQuery(\'#ssearchbox\'));" />';
        }

	$searchbuttons.="<input name=\"Submit\" id=\"searchbutton\" class=\"searchbutton\" type=\"submit\" value=\"&nbsp;&nbsp;". $lang['searchbutton']."&nbsp;&nbsp;\" />";
	hook("responsivesimplesearch");
	$searchbuttons.="</div>";
	if (!$searchbar_buttons_at_bottom){ echo $searchbuttons."<br/>"; }
	if (!$basic_simple_search) {
	// Include simple search items (if any)
	global $clear_function;
	
	$optionfields=array();
	$rendered_names=array();
	$has_value=array();

	for ($n=0;$n<count($fields);$n++)
		{
		$render=true;
		if (in_array($fields[$n]["name"],$duplicate_fields) && in_array($fields[$n]["name"],$rendered_names)) {$render=false;} # Render duplicate fields only once.
		if ($render)
			{
			$rendered_names[]=$fields[$n]["name"];
			
			# Fetch current value
			$value = '';

			if(isset($set_fields[$fields[$n]["name"]]))
                {
                $value = $set_fields[$fields[$n]["name"]];
                }

			$fields[$n]['value'] = $value;

			if($value!=='')
				{
				$has_value[]=$fields[$n]['ref'];
				}

			render_search_field($fields[$n], $value, false, 'SearchWidth', true, array(), $searched_nodes);
			}
		}
	
	if(!empty($has_value))
		{
		?>
		<script>
			jQuery(document).ready(function(){
				<?php
				// we need to trigger a change event
				foreach($has_value as $trigger_field)
					{
					?>
					jQuery("#field_<?php echo $trigger_field?>").trigger('change');
					<?php
				}
				?>
			});
		</script>
		<?php
		}
	
	?>
	<script type="text/javascript">
	function FilterBasicSearchOptions(clickedfield,resourcetype)
		{
		if (resourcetype!=0)
			{
			// When selecting resource type specific fields, automatically untick all other resource types, because selecting something from this field will never produce resources from the other resource types.
			
			// Always untick the Tick All box
			if (jQuery('#rttickallres')) {jQuery('#rttickallres').prop('checked', false);}
			<?php
			# Untick all other resource types.
			for ($n=0;$n<count($types);$n++)
				{
				?>
				if (resourcetype!=<?php echo $types[$n]["ref"]?>) {jQuery("#TickBox<?php echo $types[$n]["ref"]?>").prop('checked', false);} else {jQuery("#TickBox<?php echo $types[$n]["ref"]?>").prop('checked', true);}
				<?php
				}
				?>
			// Hide any fields now no longer relevant.	
			HideInapplicableSimpleSearchFields(false);
			}

		<?php
		// When using more than one dropdown field, automatically filter field options using AJAX
		// in a attempt to avoid blank results sets through excessive selection of filters.
		if ($simple_search_dropdown_filtering && count($optionfields)>1) { ?>
		var Filter="";
		var clickedfieldno="";
		<?php for ($n=0;$n<count($optionfields);$n++)
			{
			?>
			Filter += "<?php if ($n>0) {echo ";";} ?><?php echo htmlspecialchars($optionfields[$n]) ?>:" + jQuery('#field_<?php echo htmlspecialchars($optionfields[$n])?>').value;
			
			// Display waiting message
			if (clickedfield!='<?php echo htmlspecialchars($optionfields[$n]) ?>')
				{
				if (jQuery('field_<?php echo htmlspecialchars($optionfields[$n]) ?>').attr('selectedIndex', 0))
					{
					jQuery('field_<?php echo htmlspecialchars($optionfields[$n]) ?>').html("<option value=''><?php echo $lang["pleasewaitsmall"] ?></option>");
					}
				}
			else
				{
				clickedfieldno='<?php echo $n ?>';
				}
			<?php
			} ?>
		
		// Send AJAX post request.
		jQuery.post('<?php echo $baseurl_short?>pages/ajax/filter_basic_search_options.php?nofilter=' + encodeURIComponent(clickedfieldno) + '&filter=' + encodeURIComponent(Filter), { success: function(data, textStatus, jqXHR) {eval(data);} });
		<?php } ?>
		}
		
	function HideInapplicableSimpleSearchFields(reset)
		{
		<?php
		# Consider each of the fields. Hide if the resource type for this field is not checked
		for ($n=0;$n<count($fields);$n++)
			{
			# Check it's not a global field, we don't need to hide those
			# Also check it's not a duplicate field as those should not be toggled.
			if ($fields[$n]["resource_type"]!=0 && !in_array($fields[$n]["name"],$duplicate_fields) && (empty($simple_search_display_condition) || (!empty($simple_search_display_condition) && !in_array($fields[$n]['ref'],$simple_search_display_condition))))
				{
				?>
				if (reset)
					{
					// When clicking checkboxes, always reset any resource type specific fields.
					<?php
					switch($fields[$n]['type'])
						{
						case '7':
							?>
							document.getElementById('<?php echo htmlspecialchars($fields[$n]["name"]) ?>_category').value='';
							document.getElementById('<?php echo htmlspecialchars($fields[$n]["name"]) ?>_statusbox').innerHTML='<?php echo $lang["nocategoriesselected"]?>';
							<?php
							break;
						case '4':
						case '6':
						case '10':
							?>
							document.getElementById('field_<?php echo htmlspecialchars($fields[$n]["ref"]) ?>_year').value='';
							document.getElementById('field_<?php echo htmlspecialchars($fields[$n]["ref"]) ?>_month').value='';
							<?php
							if($searchbyday)
								{
								?>
								document.getElementById('field_<?php echo htmlspecialchars($fields[$n]["ref"]) ?>_day').value='';
								<?php
								}
							break;
						default:
							?>
							document.getElementById('field_<?php echo htmlspecialchars($fields[$n]["ref"]) ?>').value='';
							<?php
						}
					?>
					}
					
				if (document.getElementById('TickBox<?php echo $fields[$n]["resource_type"] ?>') !== null && !jQuery('#TickBox<?php echo $fields[$n]["resource_type"] ?>').prop('checked'))
					{
					document.getElementById('simplesearch_<?php echo $fields[$n]["ref"] ?>').style.display='none';
					// Also deselect it.
					<?php
					switch($fields[$n]['type'])
						{
						case '4':
						case '6':
						case '10':
							?>
							document.getElementById('field_<?php echo htmlspecialchars($fields[$n]["ref"]) ?>_year').value='';
							document.getElementById('field_<?php echo htmlspecialchars($fields[$n]["ref"]) ?>_month').value='';
							<?php
							if($searchbyday)
								{
								?>
								document.getElementById('field_<?php echo htmlspecialchars($fields[$n]["ref"]) ?>_day').value='';
								<?php
								}
							break;
						case '7':
							?>
							document.getElementById('field_<?php echo htmlspecialchars($fields[$n]["name"]) ?>').value='';
							<?php
							break;
						default:
							?>
							document.getElementById('field_<?php echo htmlspecialchars($fields[$n]["ref"]) ?>').value='';
							<?php
						}
					?>
					}
				else
					{
					<?php
					if(in_array($fields[$n]['type'],array(2,3)) || ($fields[$n]["type"]==9 && $simple_search_show_dynamic_as_dropdown))
						{
						?>
						document.getElementById('field_<?php echo $fields[$n]["ref"] ?>').disabled=false;
						<?php
						}
					?>
					document.getElementById('simplesearch_<?php echo $fields[$n]["ref"] ?>').style.display='';
					}
				<?php
				}
			}
		?>
		}	
	jQuery(document).ready(function () {	
		HideInapplicableSimpleSearchFields();
	})
	</script>
		
	<div id="basicdate" class="SearchItem"><?php if ($simple_search_date) 
   			{
				?>	
	
				 <?php  echo $lang["bydate"]?><br />
	<select id="basicyear" name="year" class="SearchWidthHalf">
	          <option selected="selected" value=""><?php echo $lang["anyyear"]?></option>
	          <?php
	          
	          
	          $y=date("Y");
	          for ($n=$y;$n>=$minyear;$n--)
	                {
	                ?><option <?php if ($n==$found_year) { ?>selected<?php } ?>><?php echo $n?></option><?php
	                }
	          ?>
	        </select> 
	
	        <?php if ($searchbyday) { ?><br /><?php } ?>
	
	        <select id="basicmonth" name="month" class="SearchWidthHalf SearchWidthRight">
	          <option selected="selected" value=""><?php echo $lang["anymonth"]?></option>
	          <?php
	          for ($n=1;$n<=12;$n++)
	                {
	                $m=str_pad($n,2,"0",STR_PAD_LEFT);
	                ?><option <?php if ($n==$found_month) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo $lang["months"][$n-1]?></option><?php
	                }
	          ?>
	
	        </select> 
	
	        <?php if ($searchbyday) { ?>
	        <select id="basicday" name="day" class="SearchWidth">
	          <option selected="selected" value=""><?php echo $lang["anyday"]?></option>
	          <?php
	          for ($n=1;$n<=31;$n++)
	                {
	                $m=str_pad($n,2,"0",STR_PAD_LEFT);
	                ?><option <?php if ($n==$found_day) { ?>selected<?php } ?> value="<?php echo $m?>"><?php echo $m?></option><?php
	                }
	          ?>
	        </select>
	        <?php } 
				}     			
     			?>
	
	
	    <?php if ($star_search && $display_user_rating_stars){?>
		<?php if (!hook("replacesearchbarstars")){?>
        <div class="SearchItem StarRatings"><?php echo $lang["starsminsearch"];?><br />
        <input type="hidden" id="starsearch" name="starsearch" class="SearchWidth" value="<?php echo htmlspecialchars($starsearch);?>">
                <?php if ($starsearch=="") {$starsearch=0;}?>           
                <div  class="RatingStars" onMouseOut="StarSearchRatingDisplay(document.getElementById('starsearch').value,'StarCurrent');">&nbsp;<?php 
                for ($z=1;$z<=5;$z++)
                        {
                        ?><a href="#" onMouseOver="StarSearchRatingDisplay(<?php echo $z?>,'StarSelect');" onClick="document.getElementById('starsearch').value=<?php echo $z?>;return false;"><span id="RatingStar-<?php echo $z?>" class="Star<?php echo ($z<=$starsearch?"Current":"Empty")?>">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></a><?php
                        }
                ?>
                </div>
        </div>
        <?php } // end hook replacesearchbarstars?>
        <?php } ?>
        

	
	
    <?php if (isset($resourceid_simple_search) and $resourceid_simple_search){ ?>
             <div class="SearchItem"><?php echo $lang["resourceid"]?><br />
             <input id="searchresourceid" name="searchresourceid" type="text" class="SearchWidth" value="" />
             </div>
    <?php } ?>


	</div>

	<script type="text/javascript">
	
	jQuery(document).ready(function(){
		jQuery('.SearchItem').easyTooltip({
			xOffset: -50,
			yOffset: 40,
			charwidth: 25,
			cssclass: "ListviewStyle"
			});
		});

	function ResetTicks() {<?php echo $clear_function?>}
	</script>
	
	<!--				
	<div class="SearchItem">By Category<br />
	<select name="Country" class="SearchWidth">
	  <option selected="selected">All</option>
	  <option>Places</option>
		<option>People</option>
	  <option>Places</option>
		<option>People</option>
	  <option>Places</option>
	</select>
	</div>
	-->
	
	<?php } ?>
	
	
	
	
	
	<?php hook("searchbarbeforebuttons"); ?>
		
	<?php if ($searchbar_buttons_at_bottom){ echo $searchbuttons; } ?>
			
  </form>
  <br />
  <?php hook("searchbarbeforebottomlinks"); ?>
  <?php if (! $disable_geocoding) { ?><p><a onClick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl?>/pages/geo_search.php"><?php echo LINK_CARET ?><?php echo $lang["geographicsearch"]?></a></p><?php } ?>
  <?php if (! $advancedsearch_disabled && !hook("advancedsearchlink")) { ?><p><a onClick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl?>/pages/search_advanced.php"><?php echo LINK_CARET ?><?php echo $lang["gotoadvancedsearch"]?></a></p><?php } ?>

  <?php hook("searchbarafterbuttons"); ?>

  <?php if ($view_new_material) { ?><p><a onClick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl?>/pages/search.php?search=<?php echo urlencode("!last".$recent_search_quantity)?>">&gt; <?php echo $lang["viewnewmaterial"]?></a></p><?php } ?>
	
	<?php } ?> <!-- END of Searchbarreplace hook -->
	</div>
	</div>
	<div class="PanelShadow"></div>
<?php } ?>	
	
	<?php if ($show_anonymous_login_panel && isset($anonymous_login) && (isset($username)) && ($username==$anonymous_login))
	{
	# For anonymous access, display the login panel
	?>
	<br /><div id="SearchBoxPanel" class="LoginBoxPanel" >
	<div class="SearchSpace">

	  <h2><?php echo $lang["login"]?></h2>

  
  <form id="simple_search_form" method="post" action="<?php echo $baseurl?>/login.php">
  <div class="SearchItem"><?php echo $lang["username"]?><br/><input type="text" name="username" id="name" class="SearchWidth" /></div>
  
  <div class="SearchItem"><?php echo $lang["password"]?><br/><input type="password" name="password" id="name" class="SearchWidth" /></div>
  <div class="SearchItem"><input name="Submit" type="submit" value="&nbsp;&nbsp;<?php echo $lang["login"]?>&nbsp;&nbsp;" /></div>
  </form>
    <p><br/><?php
	if ($allow_account_request) { ?><a href="<?php echo $baseurl_short?>pages/user_request.php">&gt; <?php echo $lang["nopassword"]?> </a></p><?php }
	if ($allow_password_reset){?><p><a href="<?php echo $baseurl_short?>pages/user_password.php">&gt; <?php echo $lang["forgottenpassword"]?></a><?php }?>
	</p>
	</div>
 
	</div>
	<div class="PanelShadow"></div>
	<?php
	}
?>
<?php hook("addsearchbarpanel");?>	
	
	<?php if (($research_request) && (!isset($k) || $k=="") && (checkperm("q"))) { ?>
	<?php if (!hook("replaceresearchrequestbox")) { ?>
	<div id="ResearchBoxPanel">
  	<div class="SearchSpace">
  	<?php if (!hook("replaceresearchrequestboxcontent"))  { ?>
	<h2><?php echo $lang["researchrequest"]?></h2>
	<p><?php echo text("researchrequest")?></p>
	<div class="HorizontalWhiteNav"><a onClick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl?>/pages/research_request.php">&gt; <?php echo $lang["researchrequestservice"]?></a></div>
	</div><br />
	<?php } /* end replaceresearchrequestboxcontent */ ?>
	</div>
	<div class="PanelShadow"></div>
	<?php } /* end replaceresearchrequestbox */ ?>
	<?php } ?>

<?php hook("searchbarbottomtoolbar"); ?>

<?php if ($swap_clear_and_search_buttons){?>
<script type="text/javascript">jQuery("#clearbutton").before(jQuery("#searchbutton"));</script>
<?php } ?>

</div>

<?php hook("searchbarbottom"); ?>
