/**
 * chat.js - Logic for the Modular Global Chat System.
 *
 * This module handles:
 * - Real-time Interaction: Mock API for fetching and sending chat messages.
 * - Social UI: Renders incoming and outgoing message bubbles with avatars.
 * - UX: Implements automated "scroll-to-bottom" behavior for new messages.
 * - Online Status: Tracks and displays the current number of active users.
 */
$(function() {
  /**
   * Internal Mock API for Chat Data.
   */
  const API = {
    getMessages: () => {
      return new Promise(resolve => {
        setTimeout(() => {
          resolve([
            { type: 'incoming', user: 'Jane Smith', initial: 'JS', time: '10:45 AM', text: 'Melt CSS is amazing!' },
            { type: 'outgoing', user: 'You', initial: 'YO', time: '10:46 AM', text: 'Thanks! We worked hard on it.' },
            { type: 'incoming', user: 'Alex Kumar', initial: 'AK', time: '10:48 AM', text: 'How can I implement modular pages?' }
          ]);
        }, 500);
      });
    },
    onlineCount: () => Promise.resolve(42)
  };

  /**
   * Appends a message bubble to the chat container.
   * @param {Object} m Message data object.
   */
  const renderMessage = (m) => {
    const html = `
      <div class="message ${m.type}">
        ${m.type === 'incoming' ? `<div class="message-avatar">${m.initial}</div>` : ''}
        <div class="message-content">
          <div class="message-info">${m.user} <span class="time">${m.time}</span></div>
          <div class="message-bubble">${m.text}</div>
        </div>
      </div>
    `;
    $('#chatMessages').append(html);
  };

  /**
   * Forces the chat window to scroll to the latest message.
   */
  const scrollToBottom = () => {
    const el = $('#chatMessages').elements[0];
    if (el) el.scrollTop = el.scrollHeight;
  };

  // --- Initial Hydration ---
  Promise.all([API.getMessages(), API.onlineCount()]).then(([messages, count]) => {
    $('#onlineCount').text(`👥 ${count} online`);
    $('.chat-header h3').html('💬 Global Chat');
    $('#chatMessages').empty();
    messages.forEach(renderMessage);
    scrollToBottom();
  });

  // --- Send Message Handler ---
  $('#chatFormPage').submit(function(e) {
    e.preventDefault();
    const input = $('#chatInput');
    const text = input.val().trim();
    if(!text) return;

    // Optimistic UI update.
    renderMessage({
      type: 'outgoing',
      user: 'You',
      time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      text: text
    });

    input.val('');
    scrollToBottom();
    showPopup("Message sent", "success");
  });
});
