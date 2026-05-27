// Restore scroll position after a smooth reload
(function () {
    const savedScroll = sessionStorage.getItem('wallos-scroll-pos');
    if (savedScroll !== null) {
        sessionStorage.removeItem('wallos-scroll-pos');
        const restore = () => window.scrollTo(0, parseInt(savedScroll, 10));
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', restore);
        } else {
            restore();
        }
    }
}());

function smoothReload() {
    sessionStorage.setItem('wallos-scroll-pos', window.scrollY);
    window.location.reload();
}

function openNotificationsSettings(type) {
    // Get all .account-notification-section-settings elements
    var sections = document.querySelectorAll('.account-notification-section-settings');
    var targetSection = document.querySelector(`.account-notification-section-settings[data-type="${type}"]`);
    
    // Remove the is-open class from all elements
    sections.forEach(function(section) {
      if (section !== targetSection) {
        section.classList.remove('is-open');
      }
    });
  
    // Add the is-open class to the element with data-type=type
  
    if (targetSection && !targetSection.classList.contains('is-open')) {
      targetSection.classList.add('is-open');
    } else {
      targetSection.classList.remove('is-open');
    }
}

function makeFetchCall(url, data, button, onSuccess) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            "X-CSRF-Token": window.csrfToken,
        },
        body: JSON.stringify(data),
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            if (onSuccess) {
                onSuccess();
            } else {
                showSuccessMessage(result.message);
                button.disabled = false;
            }
        } else {
            showErrorMessage(result.message);
            button.disabled = false;
        }
    })
    .catch((error) => {
        showErrorMessage(error);
        button.disabled = false;
    });

}

function toggleFirstNotification(enabled) {
    const settings = document.getElementById('first-notification-settings');
    if (settings) settings.style.display = enabled ? '' : 'none';
}

function toggleSecondNotification(enabled) {
    const settings = document.getElementById('second-notification-settings');
    if (settings) settings.style.display = enabled ? '' : 'none';
}

function toggleDigestMode(enabled) {
    const settings = document.getElementById('digest-mode-settings');
    if (settings) settings.style.display = enabled ? '' : 'none';
}

function saveNotifications() {
    const button = document.getElementById("saveNotifications");
    button.disabled = true;
    const days = document.querySelector('#days').value;
    const firstNotificationEnabled = document.getElementById("firstnotificationenabled").checked ? 1 : 0;
    const secondNotificationEnabled = document.getElementById("secondnotificationenabled").checked ? 1 : 0;
    const secondNotificationDays = document.getElementById("secondnotificationdays").value;
    const digestModeEnabled = document.getElementById("digestmodeenabled")?.checked ? 1 : 0;
    const digestHorizonDays = parseInt(document.getElementById("digesthorizondays")?.value || "7", 10);

    const data = {
        days: days,
        first_notification_enabled: firstNotificationEnabled,
        second_notification_enabled: secondNotificationEnabled,
        second_notification_days: secondNotificationDays,
        digest_mode_enabled: digestModeEnabled,
        digest_horizon_days: digestHorizonDays,
    };

    document.querySelectorAll('#first-notification-channels input[type=checkbox]').forEach(cb => {
        const channel = cb.id.replace('firstnotification', '');
        data['first_notification_' + channel] = cb.checked ? 1 : 0;
    });

    document.querySelectorAll('#second-notification-channels input[type=checkbox]').forEach(cb => {
        const channel = cb.id.replace('secondnotification', '');
        data['second_notification_' + channel] = cb.checked ? 1 : 0;
    });

    const url = 'endpoints/notifications/savenotificationsettings.php';
    makeFetchCall(url, data, button, smoothReload);
}

function saveNotificationsEmailButton() {
    const button = document.getElementById("saveNotificationsEmail");
    button.disabled = true;
  
    const enabled = document.getElementById("emailenabled").checked ? 1 : 0;
    const smtpAddress = document.getElementById("smtpaddress").value;
    const smtpPort = document.getElementById("smtpport").value;
    const encryption = document.querySelector('input[name="encryption"]:checked').value;
    const smtpUsername = document.getElementById("smtpusername").value;
    const smtpPassword = document.getElementById("smtppassword").value;
    const fromEmail = document.getElementById("fromemail").value;
    const otherEmails = document.getElementById("otheremails").value;
  
    const data = {
      enabled: enabled,
      smtpaddress: smtpAddress,
      smtpport: smtpPort,
      encryption: encryption,
      smtpusername: smtpUsername,
      smtppassword: smtpPassword,
      fromemail: fromEmail,
      otheremails: otherEmails
    };

    makeFetchCall('endpoints/notifications/saveemailnotifications.php', data, button, smoothReload);
}
  
function testNotificationEmailButton()  {
    const button = document.getElementById("testNotificationsEmail");
    button.disabled = true;
  
    const smtpAddress = document.getElementById("smtpaddress").value;
    const smtpPort = document.getElementById("smtpport").value;
    const encryption = document.querySelector('input[name="encryption"]:checked').value;
    const smtpUsername = document.getElementById("smtpusername").value;
    const smtpPassword = document.getElementById("smtppassword").value;
    const fromEmail = document.getElementById("fromemail").value;
  
    const data = {
      smtpaddress: smtpAddress,
      smtpport: smtpPort,
      encryption: encryption,
      smtpusername: smtpUsername,
      smtppassword: smtpPassword,
      fromemail: fromEmail
    };

    makeFetchCall('endpoints/notifications/testemailnotifications.php', data, button);
}

function saveNotificationsWebhookButton() {
    const button = document.getElementById("saveNotificationsWebhook");
    button.disabled = true;
  
    const enabled = document.getElementById("webhookenabled").checked ? 1 : 0;
    const webhook_url = document.getElementById("webhookurl").value;
    const headers = document.getElementById("webhookcustomheaders").value;
    const payload = document.getElementById("webhookpayload").value;
    const cancelation_payload = document.getElementById("webhookcancelationpayload").value;
    const ignore_ssl = document.getElementById("webhookignoressl").checked ? 1 : 0;
  
    const data = {
      enabled: enabled,
      webhook_url: webhook_url,
      headers: headers,
      payload: payload,
      cancelation_payload: cancelation_payload,
      ignore_ssl: ignore_ssl
    };

    makeFetchCall('endpoints/notifications/savewebhooknotifications.php', data, button, smoothReload);
}

function testNotificationsWebhookButton() {
    const button = document.getElementById("testNotificationsWebhook");
    button.disabled = true;
  
    const enabled = document.getElementById("webhookenabled").checked ? 1 : 0;
    const requestmethod = document.getElementById("webhookrequestmethod").value;
    const url = document.getElementById("webhookurl").value;
    const customheaders = document.getElementById("webhookcustomheaders").value;
    const payload = document.getElementById("webhookpayload").value;
    const cancelation_payload = document.getElementById("webhookcancelationpayload").value;
    const ignore_ssl = document.getElementById("webhookignoressl").checked ? 1 : 0;
  
    const data = {
      enabled: enabled,
      requestmethod: requestmethod,
      url: url,
      customheaders: customheaders,
      payload: payload,
      cancelation_payload: cancelation_payload,
      ignore_ssl: ignore_ssl
    };

    makeFetchCall('endpoints/notifications/testwebhooknotifications.php', data, button);
}

function saveNotificationsTelegramButton() {
    const button = document.getElementById("saveNotificationsTelegram");
    button.disabled = true;
  
    const enabled = document.getElementById("telegramenabled").checked ? 1 : 0;
    const chat_id = document.getElementById("telegramchatid").value;
    const bot_token = document.getElementById("telegrambottoken").value;
  
    const data = {
      enabled: enabled,
      chat_id: chat_id,
      bot_token: bot_token
    };

    makeFetchCall('endpoints/notifications/savetelegramnotifications.php', data, button, smoothReload);
}

function testNotificationsTelegramButton() {
    const button = document.getElementById("testNotificationsTelegram");
    button.disabled = true;
  
    const enabled = document.getElementById("telegramenabled").checked ? 1 : 0;
    const bottoken = document.getElementById("telegrambottoken").value;
    const chatid = document.getElementById("telegramchatid").value;
  
    const data = {
      enabled: enabled,
      bottoken: bottoken,
      chatid: chatid
    };

    makeFetchCall('endpoints/notifications/testtelegramnotifications.php', data, button);
}

function testNotificationsPushPlusButton() {
    const button = document.getElementById("testNotificationsPushPlus");
    button.disabled = true;
  
    const enabled = document.getElementById("pushplusenabled").checked ? 1 : 0;
    const token = document.getElementById("pushplustoken").value;
  
    const data = {
      enabled: enabled,
      token: token
    };

    makeFetchCall('endpoints/notifications/testpushplusnotifications.php', data, button);
}

function saveNotificationsPushPlusButton() {
    const button = document.getElementById("saveNotificationsPushPlus");
    button.disabled = true;
  
    const enabled = document.getElementById("pushplusenabled").checked ? 1 : 0;
    const token = document.getElementById("pushplustoken").value;
  
    const data = {
      enabled: enabled,
      token: token
    };

    makeFetchCall('endpoints/notifications/savepushplusnotifications.php', data, button, smoothReload);
}

function testNotificationsMattermostButton() {
    const button = document.getElementById("testNotificationsMattermost");
    button.disabled = true;
  
    const enabled = document.getElementById("mattermostenabled").checked ? 1 : 0;
    const webhook_url = document.getElementById("mattermostwebhookurl").value;
    const bot_username = document.getElementById("mattermostbotusername").value;
    const bot_icon_emoji = document.getElementById("mattermostboticonemoji").value;
  
    const data = {
      enabled: enabled,
      webhook_url: webhook_url,
      bot_username: bot_username,
      bot_icon_emoji: bot_icon_emoji
    };

    makeFetchCall('endpoints/notifications/testmattermostnotifications.php', data, button);
}

function saveNotificationsMattermostButton() {
    const button = document.getElementById("saveNotificationsMattermost");
    button.disabled = true;
  
    const enabled = document.getElementById("mattermostenabled").checked ? 1 : 0;
    const webhook_url = document.getElementById("mattermostwebhookurl").value;
    const bot_username = document.getElementById("mattermostbotusername").value;
    const bot_icon_emoji = document.getElementById("mattermostboticonemoji").value;
  
    const data = {
      enabled: enabled,
      webhook_url: webhook_url,
      bot_username: bot_username,
      bot_icon_emoji: bot_icon_emoji
    };

    makeFetchCall('endpoints/notifications/savemattermostnotifications.php', data, button, smoothReload);
}

function saveNotificationsGotifyButton() {
    const button = document.getElementById("saveNotificationsGotify");
    button.disabled = true;
  
    const enabled = document.getElementById("gotifyenabled").checked ? 1 : 0;
    const gotify_url = document.getElementById("gotifyurl").value;
    const token = document.getElementById("gotifytoken").value;
    const ignore_ssl = document.getElementById("gotifyignoressl").checked ? 1 : 0;
  
    const data = {
      enabled: enabled,
      gotify_url: gotify_url,
      token: token,
      ignore_ssl: ignore_ssl
    };

    makeFetchCall('endpoints/notifications/savegotifynotifications.php', data, button, smoothReload);
}


function testNotificationsGotifyButton() {
    const button = document.getElementById("testNotificationsGotify");
    button.disabled = true;
  
    const enabled = document.getElementById("gotifyenabled").checked ? 1 : 0;
    const gotify_url = document.getElementById("gotifyurl").value;
    const token = document.getElementById("gotifytoken").value;
    const ignore_ssl = document.getElementById("gotifyignoressl").checked ? 1 : 0;
  
    const data = {
      enabled: enabled,
      gotify_url: gotify_url,
      token: token,
      ignore_ssl: ignore_ssl
    };

    makeFetchCall('endpoints/notifications/testgotifynotifications.php', data, button);
}

function saveNotificationsPushoverButton() {
  const button = document.getElementById("saveNotificationsPushover");
  button.disabled = true;

  const enabled = document.getElementById("pushoverenabled").checked ? 1 : 0;
  const user_key = document.getElementById("pushoveruserkey").value;
  const token = document.getElementById("pushovertoken").value;

  const data = {
    enabled: enabled,
    user_key: user_key,
    token: token
  };

  makeFetchCall('endpoints/notifications/savepushovernotifications.php', data, button, smoothReload);
}

function testNotificationsPushoverButton() {
  const button = document.getElementById("testNotificationsPushover");
  button.disabled = true;

  const enabled = document.getElementById("pushoverenabled").checked ? 1 : 0;
  const user_key = document.getElementById("pushoveruserkey").value;
  const token = document.getElementById("pushovertoken").value;

  const data = {
    enabled: enabled,
    user_key: user_key,
    token: token
  };

  makeFetchCall('endpoints/notifications/testpushovernotifications.php', data, button);
}

function saveNotificationsDiscordButton() {
  const button = document.getElementById("saveNotificationsDiscord");
  button.disabled = true;

  const enabled = document.getElementById("discordenabled").checked ? 1 : 0;
  const url = document.getElementById("discordurl").value;
  const bot_username = document.getElementById("discordbotusername").value;
  const bot_avatar = document.getElementById("discordbotavatar").value;

  const data = {
    enabled: enabled,
    url: url,
    bot_username: bot_username,
    bot_avatar: bot_avatar
  };

  makeFetchCall('endpoints/notifications/savediscordnotifications.php', data, button, smoothReload);
}

function testNotificationsDiscordButton() {
  const button = document.getElementById("testNotificationsDiscord");
  button.disabled = true;

  const enabled = document.getElementById("discordenabled").checked ? 1 : 0;
  const url = document.getElementById("discordurl").value;
  const bot_username = document.getElementById("discordbotusername").value;
  const bot_avatar = document.getElementById("discordbotavatar").value;

  const data = {
    enabled: enabled,
    url: url,
    bot_username: bot_username,
    bot_avatar: bot_avatar
  };

  makeFetchCall('endpoints/notifications/testdiscordnotifications.php', data, button);
}

function testNotificationsNtfyButton() {
  const button = document.getElementById("testNotificationsNtfy");
  button.disabled = true;

  const host = document.getElementById("ntfyhost").value;
  const topic = document.getElementById("ntfytopic").value;
  const headers = document.getElementById("ntfyheaders").value;
  const ignore_ssl = document.getElementById("ntfyignoressl").checked ? 1 : 0;
  
  const data = {
    host: host,
    topic: topic,
    headers: headers,
    ignore_ssl: ignore_ssl
  };

  makeFetchCall('endpoints/notifications/testntfynotifications.php', data, button);
}

function saveNotificationsNtfyButton() {
  const button = document.getElementById("saveNotificationsNtfy");
  button.disabled = true;

  const enabled = document.getElementById("ntfyenabled").checked ? 1 : 0;
  const host = document.getElementById("ntfyhost").value;
  const topic = document.getElementById("ntfytopic").value;
  const headers = document.getElementById("ntfyheaders").value;
  const ignore_ssl = document.getElementById("ntfyignoressl").checked ? 1 : 0;

  const data = {
    enabled: enabled,
    host: host,
    topic: topic,
    headers: headers,
    ignore_ssl: ignore_ssl
  };

  makeFetchCall('endpoints/notifications/saventfynotifications.php', data, button, smoothReload);
}

function testNotificationsServerchanButton() {
  const button = document.getElementById("testNotificationsServerchan");
  button.disabled = true;

  const enabled = document.getElementById("serverchanenabled").checked ? 1 : 0;
  const sendkey = document.getElementById("serverchansendkey").value;

  const data = {
    enabled: enabled,
    sendkey: sendkey
  };

  makeFetchCall('endpoints/notifications/testserverchannotifications.php', data, button);
}

function saveNotificationsServerchanButton() {
  const button = document.getElementById("saveNotificationsServerchan");
  button.disabled = true;

  const enabled = document.getElementById("serverchanenabled").checked ? 1 : 0;
  const sendkey = document.getElementById("serverchansendkey").value;

  const data = {
    enabled: enabled,
    sendkey: sendkey
  };

  makeFetchCall('endpoints/notifications/saveserverchannotifications.php', data, button, smoothReload);
}
