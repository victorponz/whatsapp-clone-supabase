// ─────────────────────────────────────────────
//  Supabase Realtime – configuración
//  Las constantes se leen del DOM para no
//  exponer las claves en múltiples sitios.
// ─────────────────────────────────────────────
const SUPABASE_URL     = document.getElementById('supabase-config').dataset.url;
const SUPABASE_ANON_KEY = document.getElementById('supabase-config').dataset.key;
const supabaseClient   = window.supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

var userId             = document.getElementById('user').getAttribute('data-user-id');
var lastContact;
var lastContactContainer;

const templateOther  = document.getElementById('templateOther').innerHTML;
const templateMe     = document.getElementById('templateMe').innerHTML;
const templateContact = document.getElementById('templateContact').innerHTML;
const messages       = document.getElementById('messages');
const contacts       = document.getElementById('contacts');
const scrollTo       = document.getElementById('scroll-to');
var contactId        = -1;

// ─────────────────────────────────────────────
//  Suscripción al canal personal del usuario
//  Se hace una sola vez al cargar la página: por
//  "user:{id}" llegan TODOS los mensajes que envío
//  o recibo, sea cual sea el chat que tengo abierto.
// ─────────────────────────────────────────────
function subscribeToUser(myId) {
    supabaseClient
        .channel('user:' + myId)
        .on('broadcast', { event: 'chatmsg' }, function (envelope) {
            handleIncomingMessage(envelope.payload);
        })
        .subscribe();
}

// ─────────────────────────────────────────────
//  Gestión del mensaje recibido en tiempo real
// ─────────────────────────────────────────────
function handleIncomingMessage(msg) {
    var isActiveChat =
        (msg.fromUserId == userId && msg.toUserId == lastContact) ||
        (msg.fromUserId == lastContact && msg.toUserId == userId);

    if (isActiveChat) {
        createDOMMessage(msg);
        scrollTo.scrollIntoView();
    } else if (msg.toUserId == userId) {
        // El mensaje es para mí pero estoy en otro chat: mostrar badge
        showBadge(msg.fromUserId);
    }
}

// ─────────────────────────────────────────────
//  Click en un contacto
// ─────────────────────────────────────────────
function bindContactsClick() {
    $('div.contact').off();
    $('div.contact').click(function (event) {
        event.preventDefault();
        contactId = $(this).attr('data-id');

        if (lastContact != contactId) {
            loadMessages(contactId);

            $(this).removeClass('bg-white');
            $(this).addClass('bg-green-lighter border-2 border-green');

            if (lastContactContainer) {
                lastContactContainer.removeClass('bg-green-lighter border-2 border-green');
                lastContactContainer.addClass('bg-white');
            }
            lastContactContainer = $(this);
        }
        lastContact = contactId;
    });
}

// ─────────────────────────────────────────────
//  Utilidades de fecha
// ─────────────────────────────────────────────
function formatDate(datetime) {
    return new Intl.DateTimeFormat('es-ES', {
        year: 'numeric', month: 'long', day: '2-digit',
        hour: 'numeric', minute: 'numeric', second: 'numeric'
    }).format(new Date(datetime));
}

function formatContactDate(datetime) {
    return new Intl.DateTimeFormat('es-ES', {
        hour12: true, hour: 'numeric', minute: 'numeric'
    }).format(new Date(datetime));
}

// ─────────────────────────────────────────────
//  Carga de mensajes históricos
// ─────────────────────────────────────────────
function loadMessages(toUserId) {
    messages.innerHTML = '';
    $.get('/messages/from/' + toUserId, function (data) {
        data.forEach(function (m) { createDOMMessage(m); });
        scrollTo.scrollIntoView();
        $('div.contact').each(function () {
            if ($(this).attr('data-id') == toUserId) {
                $(this).find('.contact-numMessages').text('0').addClass('hidden');
            }
        });
    });
}

// ─────────────────────────────────────────────
//  Creación de elementos DOM
// ─────────────────────────────────────────────
function createDOMMessage(m) {
    var el = document.createElement('span');
    if (m.fromUserId != userId) {
        el.innerHTML = templateOther;
        el.getElementsByClassName('message.username')[0].innerHTML = m.fromUserName;
        el.getElementsByClassName('message.text')[0].innerHTML     = m.text;
        el.getElementsByClassName('message.timestamp')[0].innerHTML = formatDate(m.timestamp.date);
    } else {
        el.innerHTML = templateMe;
        el.getElementsByClassName('message.text')[0].innerHTML      = m.text;
        el.getElementsByClassName('message.timestamp')[0].innerHTML = formatDate(m.timestamp.date);
    }
    messages.appendChild(el);
}

function createDOMContact(m) {
    var el = document.createElement('span');
    el.innerHTML = templateContact;
    el.getElementsByClassName('contact.userName')[0].innerHTML = m.userName;
    el.getElementsByClassName('contact')[0].setAttribute('data-id', m.id);
    el.getElementsByClassName('contact.info')[0].innerHTML = m.info;
    el.getElementsByClassName('contact.image')[0].setAttribute('src', '/img/' + m.image);

    if (m.lastTimestamp)
        el.getElementsByClassName('contact.lastTimestamp')[0].innerHTML = formatContactDate(m.lastTimestamp.date);
    else
        el.getElementsByClassName('contact.lastTimestamp')[0].innerHTML = '--';

    contacts.appendChild(el);
    bindContactsClick();
}

function showBadge(fromUserId) {
    $('div.contact').each(function () {
        if ($(this).attr('data-id') == fromUserId) {
            var n = parseInt($(this).find('.contact-numMessages').text()) + 1;
            $(this).find('.contact-numMessages').text(n).removeClass('hidden');
        }
    });
}

// ─────────────────────────────────────────────
//  Envío de mensaje (Enter)
// ─────────────────────────────────────────────
function sendMessage(event) {
    if (event.keyCode == 13) {
        $.post('/post/touser/' + contactId, $('form#send-message').serialize(), function () {
            $('form#send-message')[0].reset();
        }, 'json');
        return true;
    }
    return false;
}

// ─────────────────────────────────────────────
//  Carga de contactos al arrancar
// ─────────────────────────────────────────────
function loadContacts() {
    messages.innerHTML = '';
    $.get('/contacts/' + userId, function (data) {
        data.forEach(function (m) { createDOMContact(m); });
        bindContactsClick();
    });
}

$(document).ready(function () {
    subscribeToUser(userId);
    loadContacts();
});
