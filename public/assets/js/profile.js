/**
 * Profile.js - Logic for the User Profile and Settings page.
 *
 * This module handles:
 * - Tab Navigation: Switches between Reading History, Library, and Account Settings.
 * - Social Actions: follow/unfollow other users with immediate UI feedback.
 * - Form Management: Updates user biography, profile/cover images, and site preferences.
 */
$(function () {
    /**
     * Switches the active UI tab.
     * @param {string} tabId ID of the pane to show (history, library, settings).
     */
    const switchTab = (tabId) => {
        $('#pTabs .profile-tab-btn').removeClass('active');
        $(`#pTabs .profile-tab-btn[data-tab="${tabId}"]`).addClass('active');
        $('.tab-pane').addClass('hidden');
        $(`#pane-${tabId}`).removeClass('hidden');
    };

    /**
     * Binds interaction events for the profile page.
     */
    const bindEvents = () => {
        // --- Tab Management ---
        $('#pTabs .profile-tab-btn').on('click', function () {
            switchTab($(this).attr('data-tab'));
        });

        // --- User-to-User Follow Action ---
        $('body').on('click', '#followBtn', async function () {
            if (!window.NMR.currentUser) return window.showPopup(window.NMR.__t('msg_login_required'), 'error');

            const btn = $(this);
            const person = window.__NMR_CONTEXT?.person || '';
            const isFollowing = btn.attr('data-status') === 'following';

            try {
                btn.prop('disabled', true);
                if (isFollowing) {
                    await Connection.unfollowUser(person);
                    btn.text(window.NMR.__t('follow')).removeClass('btn-secondary').addClass('btn-primary').attr('data-status', 'none');

                    // Optimistic counter update.
                    const count = parseInt($('#sFollowers').text().replace(/[^0-9]/g, '')) - 1;
                    $('#sFollowers').text(new Intl.NumberFormat().format(Math.max(0, count)));
                } else {
                    await Connection.followUser(person);
                    btn.text(window.NMR.__t('following')).removeClass('btn-primary').addClass('btn-secondary').attr('data-status', 'following');

                    const count = parseInt($('#sFollowers').text().replace(/[^0-9]/g, '')) + 1;
                    $('#sFollowers').text(new Intl.NumberFormat().format(count));
                }
            } catch (e) { window.showPopup(e.message, 'error'); }
            finally { btn.prop('disabled', false); }
        });

        // --- Account & Settings Update (Modal) ---
        $('body').on('submit', '#userSettingsForm', async function (e) {
            e.preventDefault();
            const btn = $(this).find('button[type="submit"]');
            const fd = new FormData(this);
            const prefData = {
                theme: fd.get('theme'),
                lang: fd.get('lang')
            };

            try {
                btn.prop('disabled', true).text(window.NMR.__t('updating'));
                // Sequential update to prevent concurrent session writes/CSRF race conditions.
                await Connection.updateProfile(fd);
                await Connection.updatePreferences(prefData);
                window.showPopup(window.NMR.__t('profile_updated'), 'success');
                setTimeout(() => location.reload(), 800);
            } catch (e) {
                window.showPopup(e.message, 'error');
                btn.prop('disabled', false).text(window.NMR.__t('update_profile'));
            }
        });

        // --- Legacy Account & Settings Update (if still in DOM) ---
        $('#settingsForm').on('submit', async function (e) {
            e.preventDefault();
            const profileData = {
                bio: $(this).find('[name="bio"]').val(),
                profile_image: $(this).find('[name="profile_image"]').val(),
                cover_image: $(this).find('[name="cover_image"]').val()
            };
            const prefData = {
                theme: $(this).find('[name="theme"]').val(),
                lang: $(this).find('[name="lang"]').val()
            };

            try {
                await Promise.all([
                    Connection.updateProfile(profileData),
                    Connection.updatePreferences(prefData)
                ]);
                window.showPopup(window.NMR.__t('profile_updated'), 'success');
                setTimeout(() => location.reload(), 800);
            } catch (e) { window.showPopup(e.message, 'error'); }
        });
    };

    bindEvents();
});
