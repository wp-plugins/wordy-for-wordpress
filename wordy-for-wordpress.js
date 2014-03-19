jQuery(function ($) {
    $(document).ready(function() {
        $('.restore-revision').removeAttr('disabled');
    });
  if ($.inArray(pagenow, [ 'page', 'post' ]) > -1) {
    getConversation();
    findRevision();
  }

  if ($.inArray(pagenow, [ 'revision' ]) > -1) {
    findRevision();
  }

  function findRevision() {
    if (wfwObject.revisionID) {
      var wordyRevision = wfwObject.revisionID;
      var revision = $('#post-revisions a[href$="revision=' + wordyRevision + '&action=edit"], #revisionsdiv a[href$="revision=' + wordyRevision + '&action=edit"]');
      revision.after(' [Wordy edit]');
      if ('revision' == pagenow) {
        revision.parent('td').siblings('.action-links').find('a').text('Use');
        var revisionCompared = $('#revision a[href$="revision=' + wordyRevision + '&action=edit"]');
        revisionCompared.after(' [Wordy edit]');
      }
    }
  }

  function getConversation() {
    $.ajax({
      url: ajaxurl,
      data: {
        action: 'get_conversation',
        post_id: wfwObject.postID,
        security : wfwObject.wfwnonce
      },
      cache: false,
      success: function(data) {
        $('.wfw-messenger .wfw-messages').html(data);
      }
    })
  }

  $('#wfw-update-conversation').click(function() {
    $.ajax({
      url: ajaxurl,
      data: {
        action: 'update_conversation',
        post_id: wfwObject.postID,
        update : $('#wfw-update-conversation-text').val(),
        security : wfwObject.wfwnonce
      },
      cache: false,
      success: function(data) {
        $('#wfw-update-conversation-text').val('')
        getConversation();
      }
    })
    return false;
  });

  $('.wordy-api').click(function() {
    $.ajax({
      url: ajaxurl,
      dataType: 'json',
      cache: false,
      data: {
        action: 'wordy_api',
        command: $(this).attr('id'),
        post_id: wfwObject.postID,
        security : wfwObject.wfwnonce
      },
      success: function(data) {
        if (data) {
          if (isNaN(parseInt(data.message))) {
            // If response is a string, update message but don't reload
            $('.wfw-notice').html(data.message);
          } else {
            // If response is an integer, add this to query string and reload
            url = window.location.href.replace(/message=[0-9]/i, '');
            window.location.href = url + '&message=' + data.message;
          }
        } else {
          // Reload only
          window.location.reload(true);
        }
      }
    })
    return false;
  });
  

  function placeholderSupport() {
    var input = document.createElement('input');
    var supported = ('placeholder' in input);
    if (!supported) {
      $('.wfw-placeholder-alternative').show();
    }
  }
  placeholderSupport();

});