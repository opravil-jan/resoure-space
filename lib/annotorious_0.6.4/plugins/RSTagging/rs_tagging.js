annotorious.plugin.RSTagging = function (opt_config_options)
    {
    console.log('RSTagging: setting config options');

    this._ANNOTATIONS_ENDPOINT = opt_config_options['annotations_endpoint'];
    this._NODES_ENDPOINT       = opt_config_options['nodes_endpoint'];
    this._RESOURCE             = opt_config_options['resource'];

    /*
    Potential feature (implemented on popup only)
    The ability to show users that a tag which was originally attached to an
    annotation is no longer part of that resource metadata and can no longer
    be searched by it. It will show a warning icon before the tag.

    IMPORTANT: if enabling this feature, just uncomment the line below and remove
    the hardcoded value for warn_tags_unsearchable
    */
    // this._WARN_TAGS_UNSEARCHABLE = opt_config_options['warn_tags_unsearchable'];
    this._WARN_TAGS_UNSEARCHABLE = false;

    // If user does not have access to any of the allowed fields to be binded to annotations,
    // put Annotorious in read-only mode (this happens in onInitAnnotator)
    this._access_to_fixed_list_fields = false;

    this._rs_fields      = [];
    this._suggested_tags = [];
    }


annotorious.plugin.RSTagging.prototype.initPlugin = function (anno)
    {
    console.log('RSTagging: initializing plugin');

    var self = this;

    // Load fields for wizard
    jQuery.get(
        this._ANNOTATIONS_ENDPOINT,
        {
        action  : 'get_allowed_fields'
        },
        function (response)
            {
            if(typeof response.error !== 'undefined' && response.error.status == 404)
                {
                console.warn('RSTagging: User has no access to allowed fields. Annotorious will continue to run in read-only mode!');
                return;
                }

            if(typeof response.data !== 'undefined' && response.data.length > 0)
                {
                self._access_to_fixed_list_fields = true;
                console.log('RSTagging: User has access to at least one allowed field. Annotorious will stop running in read-only mode!');

                for(var key in response.data)
                    {
                    self._rs_fields[key] = response.data[key];
                    }
                }
            },
        'json'
    );

    /*****************************
    * Handlers beyond this point *
    *****************************/

    // Delete annotation
    anno.addHandler('onAnnotationRemoved', function (annotation)
        {
        jQuery.post(
            self._ANNOTATIONS_ENDPOINT,
            {
            action       : 'delete',
            annotation_id: annotation.ref
            },
            function (response)
                {
                if(typeof response.data !== 'undefined' && response.data == true)
                    {
                    console.log('RSTagging: deleted annotation (ID ' + annotation.ref + ')');
                    }
                else if(typeof response.data !== 'undefined' && response.data == false)
                    {
                    console.error('RSTagging: could not delete annotation (ID ' + annotation.ref + ')');
                    }
                },
            'json');
        });
    }


annotorious.plugin.RSTagging.prototype.onInitAnnotator = function (annotator)
    {
    console.log('RSTagging: onInitAnnotator...');

    var self = this;

    // Remove the "Click and drag to annotate" we get on top of the image
    jQuery('.annotorious-hint').remove();
    console.log('RSTagging: removed .annotorious-hint element from DOM');

    // Get annotations
    jQuery.get(
        this._ANNOTATIONS_ENDPOINT,
        {
        action  : 'get_resource_annotations', 
        resource: this._RESOURCE
        },
        function (response)
            {
            if(typeof response.data !== 'undefined' && response.data.length > 0)
                {
                for(var key in response.data)
                    {
                    if(!self._access_to_fixed_list_fields)
                        {
                        response.data[key].editable = false;
                        }

                    anno.addAnnotation(response.data[key]);
                    }
                }
            },
        'json'
    );

    // Put Annotorious in read-only mode if needed
    if(!self._access_to_fixed_list_fields)
        {
        anno.hideSelectionWidget();
        console.warn('RSTagging: Running in read-only mode!');
        }

    this._extendPopup(annotator);
    this._extendEditor(annotator);
    }


annotorious.plugin.RSTagging.prototype._extendPopup = function (annotator)
    {
    console.log('RSTagging: extending Annotorious popup...');

    var self = this;

    // Remove text field from popup
    // IMPORTANT: until we implement text fields as nodes, this will have
    // to be hidden from the interface
    jQuery('.annotorious-popup-text').hide();
    console.log('RSTagging: hid .annotorious-popup-text element');

    annotator.popup.addField(function(annotation)
        {
        return self._renderAnnotationTags(annotation);
        });
    }


annotorious.plugin.RSTagging.prototype._extendEditor = function(annotator)
    {
    console.log('RSTagging: extending Annotorious editor...');

    var self = this;

    // Add wizard
    // Note: if there is only one field allowed to be used for annotations, don't bother showing the dropdown,
    // make it the only field to search and just display the text box and tags
    annotator.editor.addField(function (annotation)
        {
        return self._renderWizard(annotation);
        });

    annotator.editor.addField(function (annotation)
        {
        return self._renderAnnotationTags(annotation, false);
        });

    // Listen for keystroke events and run the search for that node name
    var search_timeout = null;
    annotator.editor.element.onkeyup = function (e)
        {
        clearTimeout(search_timeout);

        search_timeout = setTimeout(function ()
            {
            var annotation = annotator.editor.getAnnotation();

            // IMPORTANT: new annotations will not contain ResourceSpace specific information
            // console.log(annotation);

            jQuery.get(
                self._NODES_ENDPOINT,
                {
                resource_type_field: self._resource_type_field,
                name               : annotation.text
                },
                function (response)
                    {
                    if(typeof response.error !== 'undefined' && response.error.status == 400)
                        {
                        return false;
                        }

                    if(typeof response.data !== 'undefined' && response.data.length > 0)
                        {
                        self._suggested_tags = response.data;
                        annotation.tags = response.data;

                        // TODO: change _renderAnnotationTags so that it only renders the tags (as fixed/ actionable items)
                        alert('TODO: rs_tagging.js @ 210');
                        // annotator.editor.addField(self._renderAnnotationTags(annotation, false));
                        }

                    // Clear out searched text
                    jQuery('.annotorious-editor-text')[0].value = '';
                    },
                'json');
            }, 500);
        }
    }


/**
* Render tags on Annotorious popup/ editor
* 
* @param {Object} {Annotation} annotation     - Annotation object as retrieved by Annotorious or
*                                               provided by us as object literal
* @param {boolean}             [fixed = true] - By default this means we just show all tags without being
*                                               able to click on them or other functionality available
*                                               that can change their state
* 
* @returns {boolean|Element}
*/
annotorious.plugin.RSTagging.prototype._renderAnnotationTags = function (annotation, fixed)
    {
    var self = this;

    /*
    Annotorious uses one popup/ editor box for all annotations.
    We have to remove all the tags from it that may belong to an 
    old annotation we used before
    */
    jQuery('#RSTagging-tags').remove();

    if(typeof annotation.tags === 'undefined' || annotation.tags.length == 0)
        {
        return false;
        }

    if(typeof fixed === 'undefined')
        {
        fixed = true;
        }

    var tags_container = document.createElement('div');
    tags_container.setAttribute('id', 'RSTagging-tags');

    for(var key in annotation.tags)
        {
        if(!self._WARN_TAGS_UNSEARCHABLE && annotation.tags[key].tag_searchable == '')
            {
            continue;
            }

        var el = document.createElement('span');
        el.className = 'RSTagging-tag';

        // Mark tags that are not attached to a resource (ie. resource_node does not have a 
        // record for this node anymore) with a warning sign
        if(typeof annotation.tags[key].tag_searchable !== 'undefined' 
            && annotation.tags[key].tag_searchable == ''
        )
            {
            el.innerHTML = '<i class="fa fa-exclamation-triangle" aria-hidden="true"></i>';
            }

        el.innerHTML = el.innerHTML + annotation.tags[key].name;

        tags_container.appendChild(el);
        }

    var clearer = document.createElement('div');
    clearer.className = 'clearer';
    tags_container.appendChild(clearer);

    return tags_container;
    }


annotorious.plugin.RSTagging.prototype._renderWizard = function (annotation)
    {
    var self = this;

    if(!annotation)
        {
        jQuery('.annotorious-editor-text').hide();
        }

    // User has access to only one field? Let Annotorious know there is only one
    // field to use when saving annotations
    if(self._rs_fields.length == 1)
        {
        self._resource_type_field = self._rs_fields[0];
        jQuery('.annotorious-editor-text').show();

        return false;
        }

    // Multiple fields are displayed as a dropdown
    if(self._rs_fields.length > 1)
        {
        var dropdown_element = document.createElement('select');
        dropdown_element.setAttribute('id', 'RSTagging-field-selector');
        dropdown_element.onchange = function ()
            {
            jQuery('.annotorious-editor-text').hide();

            if(this.selectedIndex > 0)
                {
                jQuery('.annotorious-editor-text').show();

                self._resource_type_field = this.options[this.selectedIndex].value;
                }
            }

        // Set default option
        var option = document.createElement('option');
        dropdown_element.appendChild(option);

        for(key in self._rs_fields)
            {
            option           = document.createElement('option');
            option.innerHTML = self._rs_fields[key].title;
            option.value     = parseInt(self._rs_fields[key].ref, 10);

            // Looking at an existing annotation? Check if this is the field selected origianlly for it.
            if(annotation && option.value == annotation.resource_type_field)
                {
                option.selected = true;

                self._resource_type_field = option.value;

                jQuery('.annotorious-editor-text').show();
                }

            dropdown_element.appendChild(option);
            }
        }

    return dropdown_element;
    }