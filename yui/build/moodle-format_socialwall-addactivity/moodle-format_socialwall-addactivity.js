YUI.add('moodle-format_socialwall-addactivity', function (Y, NAME) {

M.format_socialwall = M.format_socialwall || {};
M.format_socialwall.addactivityinit = function (data) {
    "use strict";

    var dialog;
    var parentnode;
    var contentnode;
    var attachrecentactivitiyids;

    var maxwidth = 500;
    var maxheight = 500;

    function initDialog() {

        Y.one(document.body).appendChild(parentnode);

        dialog = new M.core.dialogue({
            bodyContent: contentnode,
            width: maxwidth,
            height: maxheight,
            modal: true,
            zIndex: 15,
            visible: true,
            render: true,
            center: true
        });

        var ok = {
            value: M.str.format_socialwall.attach,
            action: function (e) {
                e.preventDefault();
                onAttachActivities();
                dialog.hide();
            },
            section: Y.WidgetStdMod.FOOTER
        };

        var cancel = {
            value: M.str.format_socialwall.cancel,
            action: function (e) {
                dialog.hide();
            },
            section: Y.WidgetStdMod.FOOTER
        };

        dialog.hide();
        dialog.addButton(ok);
        dialog.addButton(cancel);

    }

    function storeRecentActivityidsToSession(attachrecentactivitiyids) {

        // Collect selected modids.
        var url = M.cfg.wwwroot + '/course/format/socialwall/pages/addactivity_ajax.php';

        // ... get params.
        var params = {};
        params.courseid = data.courseid;
        params.action = 'addactivities';
        params.attachrecentactivitiyids = Y.JSON.stringify(attachrecentactivitiyids);
        params.sesskey = M.cfg.sesskey;

        Y.io(url, {
            data: params
        });
    }

    function onAttachActivities() {

        attachrecentactivitiyids = [];

        var selectedactivities = Y.one('#attachedrecentactivities');
        selectedactivities.setHTML('');

        var recentactivities = Y.all('#id_recentactivitiesheader input[name^="module_"]');
        if (recentactivities) {

            recentactivities.each(function (node) {

                if (node.get('checked')) {

                    var id = node.get('id').split('_') [1];
                    var selectedElement = Y.one('#id_recentactivitiesheader .felement label[for="module_' + id + '"]');
                    var li = Y.Node.create('<li></li>');
                    var clone = selectedElement.cloneNode(true);
                    li.append(clone);
                    selectedactivities.append(li);

                    attachrecentactivitiyids.push(id);
                }
            });
        }

        storeRecentActivityidsToSession(attachrecentactivitiyids);

        return true;
    }

    function setCheckBoxes() {

        var recentactivities = Y.all('#id_recentactivitiesheader input[name^="module_"]');
        if (recentactivities) {

            recentactivities.each(function (node) {
                node.set('checked', false);
            });
        }

        if (attachrecentactivitiyids) {

            for (var i in attachrecentactivitiyids) {
                var id = attachrecentactivitiyids[i];
                var cb = Y.one('#module_' + id);

                if (cb) {
                    cb.set('checked', 'checked');
                }
            }
        }
    }

    function onClickFilterByType() {

        var recentactivities = Y.all('#id_recentactivitiesheader div[id^="fitem_module_"]');
        if (recentactivities) {
            recentactivities.hide();
        }

        var checkedfilterelements = Y.all('#fgroup_id_filterbytype input[id^="type_"]');

        if (checkedfilterelements) {

            checkedfilterelements.each(function (node) {

                if (node.get('checked')) {

                    var type = node.get('id').split('_') [1];

                    var shownactivities = Y.all('#id_recentactivitiesheader input[name^="module_' + type + '"]');
                    if (shownactivities) {
                        shownactivities.each(function (node) {
                            node.ancestor('.fitem').show();
                        });
                    }
                }
            });
        }

        return true;
    }

    function onResizeDialog() {

        var bb = dialog.get('boundingBox');
        var windowheight = bb.get('winHeight');
        var windowwidth = bb.get('winWidth');

        var height = Math.min(windowheight, maxheight);
        var width = Math.min(windowwidth, maxwidth);

        dialog.set('height', height);
        dialog.set('width', width - 120);

        dialog.centerDialogue();
    }

    function onChangeSearchName(searchfield) {

        var searchtext = searchfield.get('value');
        var recentactivities = Y.all('#id_recentactivitiesheader div[id^="fitem_module_"]');

        if (searchtext.length < 3) {

            if (recentactivities) {

                recentactivities.each(
                        function (node) {
                            node.show();
                        }
                );
            }
            return false;
        }

        if (!recentactivities) {
            return false;
        }

        recentactivities.each(
                function (node) {

                    var label = node.one('span.instancename');

                    if (label) {

                        var name = label.getContent();

                        var index = name.indexOf(searchtext);

                        if (name.toUpperCase().indexOf(searchtext.toUpperCase()) == -1) {
                            node.hide();
                        } else {
                            node.show();
                        }
                    } else {
                        node.hide();
                    }

                }
        );
    }

    function initialize() {

        attachrecentactivitiyids = data.attachedrecentactivities;

        parentnode = Y.Node.create('<div id="filesskin" class="format_socialwall-addactivity-dialog"></div>');
        contentnode = Y.one('#tl-addrecentactivity-formwrapper');
        parentnode.append(contentnode);

        initDialog();

        var typefilter = Y.one('#fgroup_id_filterbytype');
        if (typefilter) {
            typefilter.delegate('click', function (e) {
                onClickFilterByType();
            }, 'input[id^="type_"]');
        }

        var searchbyname = Y.one('#id_searchbyname');
        if (searchbyname) {
            searchbyname.on('keyup', function (e) {
                onChangeSearchName(this);
            });
        }

        var control = Y.one('#tl-addrecentactivity-text');
        var link = Y.one('#tl-addrecentactivity-link');

        link.append(control);
        link.show();

        link.on('click', function (e) {
            e.preventDefault();
            setCheckBoxes();
            dialog.show();
        });

        Y.one('window').on('resize', function (e) {
            onResizeDialog();
        }
        );
    }

    initialize();
};


}, '@VERSION@', {"requires": ["base", "node", "io", "moodle-core-notification-dialogue"]});
