YUI.add('moodle-format_socialwall-postform', function (Y, NAME) {

M.format_socialwall = M.format_socialwall || {};

M.format_socialwall.postforminit = function (data) {
    "use strict";

    var fileswrapper;
    var endofpostsnode;
    var loading = false;

    function doSubmit(args, spinnernode, callbacksuccess) {

        var url = M.cfg.wwwroot + '/course/format/socialwall/ajax.php';
        var spinner = M.util.add_spinner(Y, spinnernode);

        var cfg = {
            method: 'POST',
            on: {
                start: function () {

                    spinner.show();
                },
                success: function (id, resp) {
                    try {

                        var responsetext = Y.JSON.parse(resp.responseText);
                        if (responsetext.error == 0) {
                            callbacksuccess(responsetext);
                        } else {
                            alert(responsetext.error);
                        }
                        spinner.hide();

                    } catch (e) {
                        alert('parsefailed');
                        spinner.hide();
                    }
                },
                failure: function () {
                    loading = false;
                    spinner.hide();
                }
            }
        };

        if (args.data) {
            cfg.data = args.data;
        }

        if (args.form) {
            cfg.form = args.form;
        }

        Y.io(url, cfg);
    }

    function onClickLikePost(linknode) {

        // ... get params.
        var params = {};

        params.courseid = data.courseid;
        params.action = 'likepost';
        params.postid = linknode.get('id').split('_')[1];
        params.userlike = Number(linknode.hasClass('like'));
        params.sesskey = M.cfg.sesskey;

        doSubmit({
            data: params
        }, linknode, function (r) {
            callbackPostliked(r);
        });
    }

    function callbackPostliked(responsedata) {

        var likelink = Y.one('#userlike_' + responsedata.postid);
        if (responsedata.userlike == '1') {
            // likenomore
            likelink.replaceClass('like', 'likenomore');
            likelink.setHTML(M.str.format_socialwall.likenomore);

        } else {
            // like
            likelink.replaceClass('likenomore', 'like');
            likelink.setHTML(M.str.format_socialwall.like);
        }
        updatePostCounts(responsedata);

    }

    /** load more Posts */
    function onLoadMorePosts() {
        // ... get params.
        var params = {};

        params.courseid = data.courseid;
        params.action = "loadmoreposts";
        params.sesskey = M.cfg.sesskey;
        params.limitfrom = data.postsloaded;

        doSubmit({
            data: params
        }, endofpostsnode, function (r) {
            callbackMorePostsLoaded(r);
        });
    }

    /** callback after more posts were loaded*/
    function callbackMorePostsLoaded(responsedata) {

        // ...appendposts.
        var node = Y.Node.create(responsedata.postshtml);
        endofpostsnode.insert(node, 'before');

        // ...adapt counts.
        data.postsloaded = responsedata.postsloaded;
        data.poststotal = responsedata.poststotal;
        var counttotalpost = M.str.format_socialwall.counttotalpost.replace('{$a->count}', data.postsloaded)
            .replace('{$a->total}', data.poststotal);

        Y.one('#counttotalpost').setHTML(counttotalpost);

        loading = false;
    }

    /** lock posts has changed */
    function onClickLockPost(linknode) {

        var params = {};

        params.courseid = data.courseid;
        params.action = "lockpost";
        params.sesskey = M.cfg.sesskey;
        params.postid = linknode.get('id').split('_')[1];
        params.locked = Number(!linknode.hasClass('locked'));

        doSubmit({
            data: params
        }, linknode, function (r) {
            callbackPostLocked(r);
        });
    }

    /** callback after post lock chage is saved. */
    function callbackPostLocked(responsedata) {

        var showlink = Y.one('#showcommentform_' + responsedata.postid + '_0');
        var linknode = Y.one('#lockpost_' + responsedata.postid);
        var icon = linknode.one('*');

        if (responsedata.locked == '1') {

            showlink.hide();
            Y.one('#tlcommentformwrap_' + responsedata.postid + '_0').hide();
            linknode.replaceClass('unlocked', 'locked');
            icon.set('src', M.util.image_url('lockedpost', 'format_socialwall'));

        } else {

            showlink.show();
            linknode.replaceClass('locked', 'unlocked');
            M.util.image_url('unlockedpost', 'format_socialwall');
            icon.set('src', M.util.image_url('unlockedpost', 'format_socialwall'));
        }
    }

    function onClickPostComment(postbutton) {

        var postid = postbutton.get('id').split('_')[1];
        var replycommentid = postbutton.get('id').split('_')[2];
        var text = Y.one('#commenttext_' + postid + '_' + replycommentid).get('value');

        if (!text) {
            alert(M.str.format_socialwall.textrequired);
            return false;
        }

        var formobject = {
            id: 'tlcommentform_' + postid + '_' + replycommentid,
            useDisabled: true
        };
        doSubmit({
            form: formobject
        }, postbutton, function (r) {
            callbackCommentPosted(r);
        });
    }

    function updatePostCounts(responsedata) {

        var countcomments = Y.one('#tlcountcomments_' + responsedata.postid);
        if (countcomments) {
            countcomments.setHTML(M.str.format_socialwall.countcomments.replace('{$a}', responsedata.countcomments));
        }

        var countlikes = Y.one('#tlcountlikes_' + responsedata.postid);
        if (countlikes) {
            countlikes.setHTML(M.str.format_socialwall.countlikes.replace('{$a}', responsedata.countlikes));
        }
    }

    function callbackCommentPosted(responsedata) {

        var commentidentifier = responsedata.postid + '_' + responsedata.replycommentid;

        Y.one('#commenttext_' + commentidentifier).set('value', '');
        Y.one('#tlcommentformwrap_' + commentidentifier).hide();

        // get commentslist.
        var commentnode = Y.Node.create(responsedata.commenthtml);
        Y.one('#tlcomments_' + commentidentifier).prepend(commentnode);
        updatePostCounts(responsedata);
    }

    function onClickLoadAllComments(linknode) {

        var params = {};

        params.courseid = data.courseid;
        params.action = 'showallcomments';
        params.sesskey = M.cfg.sesskey;
        params.postid = linknode.get('id').split('_')[1];

        doSubmit({
            data: params
        }, linknode, function (r) {
            callbackAllCommentsLoaded(r);
        });
    }

    function callbackAllCommentsLoaded(responsedata) {

        // get commentslist.
        Y.one('#tlcomments_' + responsedata.postid + '_0').setHTML(responsedata.commentshtml);
        Y.one('#tlshowall_' + responsedata.postid).hide();
    }

    function onClickLoadAllReplies(linknode) {

        var params = {};

        params.courseid = data.courseid;
        params.action = 'showallreplies';
        params.sesskey = M.cfg.sesskey;
        params.replycommentid = linknode.get('id').split('_')[1];

        doSubmit({
            data: params
        }, linknode, function (r) {
            callbackAllRepliesLoaded(r);
        });
    }

    function callbackAllRepliesLoaded(responsedata) {

        // get replieslist.
        Y.one('#tlcomments_' + responsedata.postid + '_' + responsedata.replycommentid).setHTML(responsedata.replieshtml);
        Y.one('#tlshowallreplies_' + responsedata.replycommentid).hide();
    }

    function onClickLoadAllDiscussions(linknode) {

        var params = {};

        params.courseid = data.courseid;
        params.action = 'showalldiscussions';
        params.sesskey = M.cfg.sesskey;
        params.postid = linknode.get('id').split('_')[1];

        doSubmit({
            data: params
        }, linknode, function (r) {
            callbackAllDiscussionsLoaded(r);
        });
    }

    function callbackAllDiscussionsLoaded(responsedata) {

        var commentsnode = Y.one('#tlcomments_' + responsedata.postid + '_0');

        if (!commentsnode) {
            return false;
        }

        // get replieslist.
        Y.one('#tlcomments_' + responsedata.postid + '_0').setHTML(responsedata.commentshtml);

        var showallcomments = Y.one('#tlcomments_' + responsedata.postid + '_0 .tl-showall');
        if (showallcomments) {
            showallcomments.hide();
        }

        var showalllink = Y.one('#tlshowall_' + responsedata.postid);
        if (showalllink) {
            Y.one('#tlshowall_' + responsedata.postid).hide();
        }

        return true;
    }

    function deleteComment(linknode) {

        var params = {};

        params.courseid = data.courseid;
        params.action = 'deletecomment';
        params.sesskey = M.cfg.sesskey;
        params.cid = linknode.get('id').split('_')[1];

        doSubmit({
            data: params
        }, linknode, function (r) {
            callbackCommentDeleted(r);
        });
    }

    function onClickDeleteComment(linknode) {

        var confirm = new M.core.confirm({
            title: M.util.get_string('confirm', 'moodle'),
            question: M.util.get_string('confirmdeletecomment', 'format_socialwall'),
            yesLabel: M.util.get_string('yes', 'moodle'),
            noLabel: M.util.get_string('cancel', 'moodle')
        });

        confirm.on('complete-yes', function () {
            confirm.hide();
            confirm.destroy();
            deleteComment(linknode);
        });
        confirm.show();
    }

    function callbackCommentDeleted(responsedata) {

        Y.one('#tlcomment_' + responsedata.commentid).remove(true);
        updatePostCounts(responsedata);
    }

    /** hide filemanger and show external file link */
    function onAddaLinkClick() {
        Y.one('#externalurlwrapper').show();
        fileswrapper.hide();
        var fm = Y.one('#loadfilemanager');
        if (fm) {
            fm.set('value', '0');
        }
    }

    /** hide externalurl edit and show filemanager*/
    function onUploadfileClick() {
        Y.one('#externalurlwrapper').hide();
        fileswrapper.show();
        var fm = Y.one('#loadfilemanager');
        if (fm) {
            fm.set('value', '1');
        }
    }

    /** gather all attached moduleids and set value of cmsequence */
    function onSubmit() {

        var section = Y.one('#section-2');
        var recentmodulelist = Y.one('#attachedrecentactivities');

        if ((!section) || (!recentmodulelist)) {
            return false;
        }

        var moduleids = [];

        // Gather new moduleids.
        section.all('li[id^="module-"]').each(
                function (node) {
                    moduleids[moduleids.length] = node.get('id').split('-')[1];
                }
        );

        // Gather recent moduleids.
        recentmodulelist.all('label[for^="module_"]').each(
                function (node) {
                    moduleids[moduleids.length] = node.get('for').split('_')[1];
                }
        );

        var cmsequence = moduleids.join(",");

        Y.one('#cmsequence').set('value', cmsequence);
        return true;
    }

    /** detect scrolling and load more posts */
    function onScroll() {

        var dist = window.innerHeight - (endofpostsnode.getY() - window.pageYOffset);

        if (!loading && dist > 50) {

            if (data.postsloaded < data.poststotal) {

                // ... use loading as a semaphor to block concurrent requests.
                loading = true;
                onLoadMorePosts();
            }
        }
    }

    /** save the formstatus in the session to keep inputed values, this sould only work for
     *  user, which are allowed to edit the page (i. e. add some activities).
     */
    function saveValuesInSession(syncrequest) {

        // first check whether the user has inputed something in to formfields
        var params = {};

        params.posttext = Y.one('#posttext').get('value');
        params.togroupid = Y.one('#id_togroupid').get('value');

        var postelement = Y.one('#id_poststatus');
        if (postelement) {
            params.poststatus = postelement.get('value');
        } else {
            params.poststatus = 0;
        }

        params.postid = Y.one('#id').get('value');
        params.courseid = data.courseid;
        params.action = 'storeformparams';
        params.sesskey = M.cfg.sesskey;

        var url = M.cfg.wwwroot + '/course/format/socialwall/ajax.php';

        Y.io(url, {
            data: params,
            sync: syncrequest
        });

    }

    /** initialize all necessary objects */
    function initialize() {

        // ... delegate events for ajax loaded elements.
        Y.one('#tl-posts').delegate('click', function (e) {
            e.preventDefault();
            onClickLockPost(e.currentTarget);
        }, 'a[id^="lockpost_"]');

        Y.one('#tl-posts').delegate('click', function (e) {
            e.preventDefault();
            onClickLikePost(e.target);
        }, 'a[id^="userlike_"]');

        Y.one('#tl-posts').delegate('click', function (e) {
            e.preventDefault();
            onClickPostComment(e.target);
        }, 'input[id^="postcomment_"]');

        Y.one('#tl-posts').delegate('click', function (e) {
            e.preventDefault();
            onClickLoadAllComments(e.target);
        }, 'a[id^="tlshowall_"]');

        Y.one('#tl-posts').delegate('click', function (e) {
            e.preventDefault();
            onClickLoadAllReplies(e.target);
        }, 'a[id^="tlshowallreplies_"]');

        Y.one('#tl-posts').delegate('click', function (e) {
            e.preventDefault();
            onClickLoadAllDiscussions(e.target);
        }, 'a[id^="tlshowalldiscussions_"]');

        Y.one('#tl-posts').delegate('click', function (e) {
            e.preventDefault();
            onClickDeleteComment(e.currentTarget);
        }, 'a[id^="tldeletecomment_"]');

        Y.one('#tl-posts').delegate('click', function (e) {
            e.preventDefault();
            var postid = e.target.get('id').split('_') [1];
            var replycommentid = e.target.get('id').split('_') [2];
            Y.one('#tlcommentformwrap_' + postid + '_' + replycommentid).show();
        }, 'a[id^="showcommentform_"]');

        // ... not lazy loaded postform elements

        var addlink = Y.one('#addalink');

        if (addlink) {
            addlink.on('click', function (e) {
                e.preventDefault();
                onAddaLinkClick();
            });
        }

        fileswrapper = Y.one('#fileswrapper');

        if (fileswrapper) {
            Y.one('#uploadfile').on('click', function (e) {
                e.preventDefault();
                onUploadfileClick();
            });
        }

        Y.one('#id_submitbutton').on('click', function () {
            onSubmit();
        });

        // ...scrolling.
        endofpostsnode = Y.one('#tl-endofposts');

        Y.on('scroll', function () {
            onScroll();
        });

        // no warnings, when leaving the page.
        window.onbeforeunload = null;

        Y.all('.section-modchooser-link').on('click',
                function () {
                    saveValuesInSession(false);
                }
        );

        var posttext = Y.one('#posttext');
        if (posttext) {
            posttext.on('change',
                    function () {
                        saveValuesInSession(false);
                    }
            );
        }

        var togroupid = Y.one('#id_togroupid');
        if (togroupid) {
            togroupid.on('change',
                    function () {
                        saveValuesInSession(false);
                    }
            );
        }

        var poststatus = Y.one('#id_poststatus');
        if (poststatus) {
            poststatus.on('change',
                    function () {
                        saveValuesInSession(false);
                    }
            );
        }
    }

    initialize();
};


}, '@VERSION@', {"requires": ["base", "node", "io-form", "moodle-core-notification-confirm"]});
