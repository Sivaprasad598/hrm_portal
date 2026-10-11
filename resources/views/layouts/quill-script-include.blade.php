<script>
    let quillArray = {};

    @if (in_array('aitools', user_modules()))
        @includeIf('aitools::includes.quill-rephrase')
    @endif

    function quillImageLoad(ID) {
        const quillContainer = document.querySelector(ID);
        quillArray[ID] = new Quill(ID, {
            modules: {
                toolbar: [
                    [{
                        header: [1, 2, 3, 4, 5, false]
                    }],
                    [{
                        'list': 'ordered'
                    }, {
                        'list': 'bullet'
                    }],
                    [{ align: '' }, { align: 'center' }, { align: 'right' }, { align: 'justify' }],
                    ['bold', 'italic', 'underline', 'strike'],
                    ['image', 'link', 'video'],
                    [{ color: [] }, { background: [] }],
                    [{
                        'direction': 'rtl'
                    }],
                    ['clean']
                ],
                clipboard: {
                    matchVisual: false
                },
                "emoji-toolbar": true,
                "emoji-textarea": true,
                "emoji-shortname": true,
            },
            theme: 'snow',
            bounds: quillContainer
        });
        $.each(quillArray, function (key, quill) {
            quill.getModule('toolbar').addHandler('image', selectLocalImage);
        });

        // Add custom rephrase button to toolbar (only if Aitools module is enabled)
        if (typeof addRephraseButton === 'function') {
            addRephraseButton(quillArray[ID], ID);
        }

        // Add paste handler for image upload
        setupQuillPasteHandler(quillArray[ID], ID);
    }

    function destory_editor(selector) {
        if ($(selector)[0]) {
            var content = $(selector).find('.ql-editor').html();
            $(selector).html(content);

            $(selector).siblings('.ql-toolbar').remove();
            $(selector + " *[class*='ql-']").removeClass(function (index, class_name) {
                return (class_name.match(/(^|\s)ql-\S+/g) || []).join(' ');
            });

            $(selector + "[class*='ql-']").removeClass(function (index, class_name) {
                return (class_name.match(/(^|\s)ql-\S+/g) || []).join(' ');
            });
        } else {
            console.error('editor not exists');
        }
    }

    function quillMention(atValues, ID) {
        const mentionItemTemplate = '<div class="mention-item"> <img src="{image}" class="mr-3 rounded align-self-start taskEmployeeImg">{name}</div>';

        const customRenderItem = function (item, searchTerm) {
            const html = mentionItemTemplate.replace('{image}', item.image).replace('{name}', item.value);
            return html;
        }
        let placeholder;
        if (ID === '#submitTexts') {
            placeholder = "@lang('placeholders.message')";
        } else {
            placeholder = '';
        }

        const quillContainer = document.querySelector(ID);

        quillArray[ID] = new Quill(ID, {
            placeholder: placeholder,
            modules: {
                magicUrl: {
                    urlRegularExpression: /(https?:\/\/[\S]+)|(www.[\S]+)|(tel:[\S]+)/g,
                    globalRegularExpression: /(https?:\/\/|www\.|tel:)[\S]+/g,
                },
                toolbar: [
                    [{
                        header: [1, 2, 3, 4, 5, false]
                    }],
                    [{
                        'list': 'ordered'
                    }, {
                        'list': 'bullet'
                    }],
                    [{ align: '' }, { align: 'center' }, { align: 'right' }, { align: 'justify' }],
                    ['bold', 'italic', 'underline', 'strike'],
                    ['image', 'link', 'video'],
                    [{ color: [] }, { background: [] }],
                    [{
                        'direction': 'rtl'
                    }],
                    ['clean']
                ],
                mention: {
                    allowedChars: /^[A-Za-z\sÅÄÖåäö]*$/,
                    mentionDenotationChars: ["@", "#"],
                    source: function (searchTerm, renderList, mentionChar) {
                        let values;
                        if (mentionChar === "@") {
                            values = atValues || [];
                        } else {
                            values = typeof hashValues !== 'undefined' ? hashValues : [];
                        }

                        if (searchTerm.length === 0) {
                            renderList(values, searchTerm);

                        } else {
                            const matches = [];
                            for (let i = 0; i < values.length; i++)
                                if (
                                    ~values[i].value
                                        .toLowerCase()
                                        .indexOf(searchTerm.toLowerCase())
                                )
                                    matches.push(values[i]);
                            renderList(matches, searchTerm);
                        }
                    },
                    renderItem: customRenderItem,

                },
                clipboard: {
                    matchVisual: false
                },
                "emoji-toolbar": true,
                "emoji-textarea": true,
                "emoji-shortname": true,
            },
            theme: 'snow',
            bounds: quillContainer
        });

        quillArray[ID].getModule('toolbar').addHandler('image', selectLocalImage);

        // Add custom rephrase button to toolbar (only if Aitools module is enabled)
        if (typeof addRephraseButton === 'function') {
            addRephraseButton(quillArray[ID], ID);
        }

        // Add paste handler for image upload
        setupQuillPasteHandler(quillArray[ID], ID);
    }

    /**
     * click to open user profile
     *
     */
    window.addEventListener('mention-clicked', function ({value}) {
        if (value?.link) {
            window.open(value.link, value?.target ?? '_blank');
        }
    });

    /**
     * Setup paste handler for Quill editor to intercept image pastes
     *
     * @param {Quill} quillInstance
     * @param {string} editorId
     */
    function setupQuillPasteHandler(quillInstance, editorId) {
        const editorElement = quillInstance.root;
        let isProcessingPaste = false;

        // Listen for paste events
        editorElement.addEventListener('paste', function(e) {
            const clipboardData = e.clipboardData || e.originalEvent?.clipboardData;

            if (!clipboardData) {
                return;
            }

            // Check if files are being pasted
            const items = clipboardData.items;
            let hasImage = false;

            for (let i = 0; i < items.length; i++) {
                const item = items[i];

                // Check if the pasted item is an image
                if (item.type.indexOf('image') !== -1) {
                    hasImage = true;
                    e.preventDefault();
                    e.stopPropagation();

                    const file = item.getAsFile();
                    if (file && /^image\//.test(file.type)) {
                        isProcessingPaste = true;
                        // Get current selection before paste
                        const range = quillInstance.getSelection(true);
                        // Upload the file to server
                        saveToServerWithQuill(file, quillInstance, range);
                    }
                    return;
                }
            }

            // Check if HTML content contains base64 images
            if (!hasImage) {
                const htmlData = clipboardData.getData('text/html');
                if (htmlData) {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(htmlData, 'text/html');
                    const images = doc.querySelectorAll('img');

                    if (images.length > 0) {
                        let hasBase64Image = false;
                        images.forEach(function(img) {
                            const src = img.getAttribute('src');
                            if (src && src.startsWith('data:image/')) {
                                hasBase64Image = true;
                            }
                        });

                        if (hasBase64Image) {
                            e.preventDefault();
                            e.stopPropagation();
                            isProcessingPaste = true;
                            const range = quillInstance.getSelection(true);

                            images.forEach(function(img, index) {
                                const src = img.getAttribute('src');
                                if (src && src.startsWith('data:image/')) {
                                    const file = base64ToFile(src);
                                    if (file) {
                                        // Add small delay for multiple images
                                        setTimeout(function() {
                                            saveToServerWithQuill(file, quillInstance, range);
                                        }, index * 100);
                                    }
                                }
                            });
                            return;
                        }
                    }
                }
            }
        });

        // Monitor for base64 images that might have been inserted by Quill's default handler
        // This is a fallback in case the paste event handler doesn't catch everything
        let isReplacingBase64 = false;
        quillInstance.on('text-change', function(delta, oldDelta, source) {
            // Skip if we're already processing or if this is from our own operations
            if (isProcessingPaste || isReplacingBase64 || source !== 'user') {
                return;
            }

            // Check if any operations contain base64 images
            if (delta && delta.ops) {
                delta.ops.forEach(function(op) {
                    if (op.insert && typeof op.insert === 'object' && op.insert.image) {
                        const imageSrc = op.insert.image;
                        if (imageSrc && imageSrc.startsWith('data:image/')) {
                            // Found a base64 image, convert and upload
                            const file = base64ToFile(imageSrc);
                            if (file) {
                                isReplacingBase64 = true;
                                isProcessingPaste = true;

                                // Get current contents to find the image position
                                const contents = quillInstance.getContents();
                                let imagePosition = 0;
                                let found = false;

                                for (let i = 0; i < contents.ops.length && !found; i++) {
                                    if (contents.ops[i].insert && typeof contents.ops[i].insert === 'object' && contents.ops[i].insert.image === imageSrc) {
                                        found = true;
                                        break;
                                    }
                                    if (contents.ops[i].insert) {
                                        if (typeof contents.ops[i].insert === 'string') {
                                            imagePosition += contents.ops[i].insert.length;
                                        } else {
                                            imagePosition += 1;
                                        }
                                    }
                                }

                                // Delete the base64 image and replace with server URL
                                setTimeout(function() {
                                    try {
                                        quillInstance.deleteText(imagePosition, 1, 'user');
                                        // Upload and insert server URL
                                        saveToServerWithQuill(file, quillInstance, { index: imagePosition });
                                    } catch (err) {
                                        console.error('Error replacing base64 image:', err);
                                    } finally {
                                        setTimeout(function() {
                                            isReplacingBase64 = false;
                                            isProcessingPaste = false;
                                        }, 500);
                                    }
                                }, 100);
                            }
                        }
                    }
                });
            }
        });
    }

    /**
     * Convert base64 string to File object
     *
     * @param {string} base64String
     * @returns {File|null}
     */
    function base64ToFile(base64String) {
        try {
            const arr = base64String.split(',');
            const mime = arr[0].match(/:(.*?);/)[1];
            const bstr = atob(arr[1]);
            let n = bstr.length;
            const u8arr = new Uint8Array(n);

            while (n--) {
                u8arr[n] = bstr.charCodeAt(n);
            }

            // Generate filename with timestamp
            const filename = 'pasted-image-' + Date.now() + '.' + mime.split('/')[1];

            return new File([u8arr], filename, { type: mime });
        } catch (e) {
            console.error('Error converting base64 to file:', e);
            return null;
        }
    }

    /**
     * Save file to server with specific quill instance
     *
     * @param {File} file
     * @param {Quill} quillInstance
     * @param {Object} range - Optional range object with index property
     */
    function saveToServerWithQuill(file, quillInstance, range) {
        const fd = new FormData();
        fd.append('image', file);
        $.ajax({
            type: 'POST',
            url: "{{ route('image.store') }}",
            dataType: "json",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: fd,
            contentType: false,
            processData: false,
            success: function (response) {
                // Insert image at specified position or current cursor position
                try {
                    let insertIndex;
                    if (range && typeof range.index !== 'undefined') {
                        insertIndex = range.index;
                    } else {
                        const currentRange = quillInstance.getSelection(true);
                        insertIndex = currentRange ? currentRange.index : 0;
                    }

                    quillInstance.insertEmbed(insertIndex, 'image', response);
                    quillInstance.setSelection(insertIndex + 1);
                } catch (err) {
                    console.error('Error inserting image:', err);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error uploading image:', error);
            }
        });
    }

    /**
     * Step1. select local image
     *
     */
    function selectLocalImage() {
        const input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.click();

        // Listen upload local image and save to server
        input.onchange = () => {
            const file = input.files[0];

            // file type is only image.
            if (/^image\//.test(file.type)) {
                saveToServer(file);
            } else {
                console.warn('You could only upload images.');
            }
        };
    }

    /**
     * Step2. save to server
     *
     * @param {File} file
     */
    function saveToServer(file) {
        const fd = new FormData();
        fd.append('image', file);
        $.ajax({
            type: 'POST',
            url: "{{ route('image.store') }}",
            dataType: "json",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: fd,
            contentType: false,
            processData: false,
            success: function (response) {
                insertToEditor(response)
            },
        });
    }

    function insertToEditor(url) {
        // push image url to rich editor.
        $.each(quillArray, function (key, quill) {
            try {
                let range = quill.getSelection();
                quill.insertEmbed(range.index, 'image', url);
            } catch (err) {
            }
        });
    }

    function checkboxChange(parentClass, id) {
        var checkedData = '';
        $('.' + parentClass).find("input[type='checkbox']:checked").each(function() {
            checkedData = (checkedData !== '') ? checkedData + ', ' + $(this).val() : $(this).val();
        });
        $('#' + id).val(checkedData);
    }
</script>
