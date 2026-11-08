const messagesApp = document.querySelector('[data-messages-app]');

if (messagesApp) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const currentUserId = Number(messagesApp.dataset.userId);
    const activeConversationId = Number(messagesApp.dataset.conversationId || 0);
    const inboxUrl = messagesApp.dataset.inboxUrl;
    const itemsUrl = messagesApp.dataset.itemsUrl;
    const readUrl = messagesApp.dataset.readUrl;
    const history = messagesApp.querySelector('[data-message-history]');
    const inboxList = messagesApp.querySelector('[data-conversation-list]');
    const inboxTotal = messagesApp.querySelector('[data-conversation-total]');
    const loadingState = messagesApp.querySelector('[data-messages-loading]');
    const failureState = messagesApp.querySelector('[data-messages-failure]');
    const newMessagesButton = messagesApp.querySelector('[data-new-messages]');
    const readForm = messagesApp.querySelector('[data-read-form]');
    const renderedMessageIds = new Set(
        [...messagesApp.querySelectorAll('[data-message-id]')]
            .map((node) => Number(node.dataset.messageId))
            .filter(Number.isFinite),
    );

    let pollingTimer = null;
    let pollingController = null;
    let pollingGeneration = 0;
    let pollingDelay = 3000;
    let lastReadThroughId = 0;
    let readRequestInFlight = false;
    let visibilityFrame = null;

    const newestRenderedId = () => renderedMessageIds.size > 0
        ? Math.max(...renderedMessageIds)
        : 0;

    const freshUuid = () => {
        if (window.crypto?.randomUUID) return window.crypto.randomUUID();

        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
            const random = Math.floor(Math.random() * 16);
            const value = character === 'x' ? random : (random & 0x3) | 0x8;
            return value.toString(16);
        });
    };

    const errorMessageForStatus = (status, payload = {}) => {
        if (status === 401) return 'Your session has expired. Sign in again to continue.';
        if (status === 419) return 'This page has expired. Refresh it before sending again.';
        if (status === 403) return 'You no longer have access to this conversation.';
        if (status === 429) return 'Too many requests. Wait a moment and try again.';
        if (status === 422 && payload.errors) {
            return Object.values(payload.errors).flat().join(' ');
        }
        if (status === 409) return payload.message || 'This draft conflicts with an earlier send. Create a new draft and try again.';

        return payload.message || 'Something went wrong. Please try again.';
    };

    const fetchJson = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...(options.headers || {}),
            },
        });
        let payload = {};

        try {
            payload = await response.json();
        } catch {
            payload = {};
        }

        if (!response.ok) {
            const error = new Error(errorMessageForStatus(response.status, payload));
            error.status = response.status;
            error.payload = payload;
            throw error;
        }

        return payload;
    };

    const isNearBottom = () => {
        if (!history) return true;

        return history.scrollHeight - history.scrollTop - history.clientHeight < 110;
    };

    const scrollToBottom = (behavior = 'smooth') => {
        if (!history) return;

        history.scrollTo({ top: history.scrollHeight, behavior });
        if (newMessagesButton) newMessagesButton.hidden = true;
    };

    const formatTime = (isoValue) => {
        const value = new Date(isoValue);

        if (Number.isNaN(value.getTime())) return '';

        return new Intl.DateTimeFormat(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        }).format(value);
    };

    const createMessageNode = (message) => {
        const article = document.createElement('article');
        article.className = `message-row ${message.is_mine ? 'is-mine' : 'is-theirs'}`;
        article.dataset.messageId = String(message.id);
        article.dataset.messageDirection = message.is_mine ? 'outgoing' : 'incoming';

        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';

        const body = document.createElement('p');
        body.textContent = message.body;

        const footer = document.createElement('footer');
        const time = document.createElement('time');
        time.dateTime = message.created_at;
        time.textContent = formatTime(message.created_at);
        footer.append(time);

        if (message.is_mine) {
            const receipt = document.createElement('span');
            receipt.dataset.messageReceipt = '';
            receipt.textContent = 'Read';
            receipt.hidden = true;
            footer.append(receipt);
        }

        bubble.append(body, footer);
        article.append(bubble);

        return article;
    };

    const appendMessage = (message, forceScroll = false) => {
        if (!history || renderedMessageIds.has(Number(message.id))) return false;

        const shouldFollow = forceScroll || isNearBottom();
        history.append(createMessageNode(message));
        renderedMessageIds.add(Number(message.id));

        if (shouldFollow) {
            scrollToBottom(forceScroll ? 'smooth' : 'auto');
        } else if (newMessagesButton) {
            newMessagesButton.hidden = false;
        }

        scheduleVisibleReadCheck();

        return true;
    };

    const updateReadReceipts = (throughId) => {
        const watermark = Number(throughId || 0);

        messagesApp.querySelectorAll('[data-message-direction="outgoing"][data-message-id]').forEach((row) => {
            const receipt = row.querySelector('[data-message-receipt]');
            if (receipt) receipt.hidden = Number(row.dataset.messageId) > watermark;
        });
    };

    const createConversationNode = (conversation) => {
        const link = document.createElement('a');
        link.className = 'conversation-list-item';
        link.href = conversation.url;
        link.dataset.conversationId = String(conversation.id);

        const avatar = document.createElement('span');
        avatar.className = 'avatar';
        avatar.setAttribute('aria-hidden', 'true');
        avatar.textContent = conversation.counterpart.name.slice(0, 1);

        const preview = document.createElement('span');
        preview.className = 'conversation-preview';
        const topline = document.createElement('span');
        topline.className = 'conversation-preview-topline';
        const name = document.createElement('strong');
        name.dataset.conversationName = '';
        const property = document.createElement('small');
        property.dataset.conversationProperty = '';
        const latest = document.createElement('span');
        latest.dataset.conversationPreview = '';

        topline.append(name);
        preview.append(topline, property, latest);
        link.append(avatar, preview);

        return link;
    };

    const updateConversationNode = (node, conversation) => {
        node.href = conversation.url;
        node.classList.toggle('is-selected', Number(conversation.id) === activeConversationId);
        if (Number(conversation.id) === activeConversationId) {
            node.setAttribute('aria-current', 'page');
        } else {
            node.removeAttribute('aria-current');
        }

        const avatar = node.querySelector('.avatar');
        const name = node.querySelector('[data-conversation-name]');
        const property = node.querySelector('[data-conversation-property]');
        const preview = node.querySelector('[data-conversation-preview]');
        const topline = node.querySelector('.conversation-preview-topline');
        let unread = node.querySelector('[data-conversation-unread]');

        if (avatar) avatar.textContent = conversation.counterpart.name.slice(0, 1);
        if (name) name.textContent = conversation.counterpart.name;
        if (property) property.textContent = conversation.property.title;
        if (preview) preview.textContent = conversation.latest_message?.body || 'Conversation started';

        if (conversation.unread_count > 0) {
            if (!unread && topline) {
                unread = document.createElement('span');
                unread.className = 'conversation-unread';
                unread.dataset.conversationUnread = '';
                topline.append(unread);
            }
            if (unread) unread.textContent = String(conversation.unread_count);
        } else {
            unread?.remove();
        }
    };

    const reconcileInbox = (payload) => {
        if (!inboxList || !Array.isArray(payload.data)) return;

        const emptyState = inboxList.parentElement?.querySelector('.messages-empty-state');
        if (payload.data.length > 0) emptyState?.remove();
        if (inboxTotal && payload.meta) inboxTotal.textContent = String(payload.meta.total);

        payload.data.forEach((conversation) => {
            let node = inboxList.querySelector(`[data-conversation-id="${conversation.id}"]`);
            if (!node) {
                node = createConversationNode(conversation);
            }
            updateConversationNode(node, conversation);
            inboxList.append(node);
        });
    };

    const pollInbox = async (signal) => {
        if (!inboxUrl) return;
        const payload = await fetchJson(inboxUrl, { signal });
        reconcileInbox(payload);
    };

    const pollMessages = async (signal) => {
        if (!itemsUrl || !history) return;

        let cursor = newestRenderedId();
        let hasMore;

        do {
            const url = new URL(itemsUrl, window.location.origin);
            url.searchParams.set('after_id', String(cursor));
            const payload = await fetchJson(url, { signal });

            payload.data.forEach((message) => appendMessage(message));
            updateReadReceipts(payload.partner_read_through_id);
            hasMore = Boolean(payload.has_more);
            cursor = Number(payload.after_id || cursor);
        } while (hasMore && !signal.aborted);
    };

    const showPollingFailure = (error) => {
        if (!failureState || error.name === 'AbortError') return;
        failureState.textContent = error.message;
        failureState.hidden = false;
    };

    const schedulePoll = (delay = pollingDelay) => {
        window.clearTimeout(pollingTimer);
        if (document.hidden) return;
        pollingTimer = window.setTimeout(runPollingCycle, delay);
    };

    const runPollingCycle = async () => {
        if (document.hidden) return;

        pollingController?.abort();
        pollingController = new AbortController();
        const generation = ++pollingGeneration;

        try {
            if (loadingState) loadingState.hidden = false;
            await Promise.all([
                pollInbox(pollingController.signal),
                pollMessages(pollingController.signal),
            ]);
            if (generation !== pollingGeneration) return;
            pollingDelay = 3000;
            if (failureState) failureState.hidden = true;
        } catch (error) {
            if (generation !== pollingGeneration || error.name === 'AbortError') return;
            showPollingFailure(error);
            pollingDelay = [401, 403, 419].includes(error.status)
                ? 30000
                : Math.min(Math.max(pollingDelay * 2, 6000), 30000);
        } finally {
            if (generation === pollingGeneration) {
                if (loadingState) loadingState.hidden = true;
                schedulePoll();
            }
        }
    };

    const submitMessageForm = async (form) => {
        const submitButton = form.querySelector('[type="submit"]');
        const body = form.querySelector('[name="body"]');
        const uuid = form.querySelector('[name="client_message_id"]');
        const status = form.querySelector('[data-form-status]');
        const formData = new FormData(form);

        submitButton.disabled = true;
        if (status) {
            status.textContent = 'Sending…';
            status.classList.remove('is-error');
            status.hidden = false;
        }

        try {
            const payload = await fetchJson(form.action, {
                method: form.method || 'POST',
                body: formData,
            });

            if (form.hasAttribute('data-start-conversation')) {
                window.location.assign(payload.conversation.url);
                return;
            }

            appendMessage(payload.message, true);
            if (body) body.value = '';
            if (uuid) uuid.value = freshUuid();
            if (status) {
                status.textContent = 'Message sent.';
                status.classList.remove('is-error');
            }
            await pollInbox(new AbortController().signal);
        } catch (error) {
            if (status) {
                status.textContent = error.message;
                status.classList.add('is-error');
                status.hidden = false;
            }
            body?.focus();
        } finally {
            submitButton.disabled = false;
        }
    };

    messagesApp.querySelectorAll('[data-message-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submitMessageForm(form);
        });
    });

    const loadOlderLink = messagesApp.querySelector('[data-load-older]');
    loadOlderLink?.addEventListener('click', async (event) => {
        event.preventDefault();
        if (!history) return;

        const previousHeight = history.scrollHeight;
        loadOlderLink.setAttribute('aria-disabled', 'true');
        loadOlderLink.textContent = 'Loading older messages…';

        try {
            const payload = await fetchJson(loadOlderLink.href);
            const fragment = document.createDocumentFragment();

            payload.data.forEach((message) => {
                if (renderedMessageIds.has(Number(message.id))) return;
                renderedMessageIds.add(Number(message.id));
                fragment.append(createMessageNode(message));
            });

            const firstMessage = history.querySelector('[data-message-id]');
            history.insertBefore(fragment, firstMessage);
            history.scrollTop += history.scrollHeight - previousHeight;
            updateReadReceipts(payload.partner_read_through_id);

            if (payload.has_more && payload.before_id) {
                const url = new URL(itemsUrl, window.location.origin);
                url.searchParams.set('before_id', String(payload.before_id));
                loadOlderLink.href = url.toString();
                loadOlderLink.textContent = 'Load older messages';
                loadOlderLink.removeAttribute('aria-disabled');
            } else {
                loadOlderLink.remove();
            }
        } catch (error) {
            loadOlderLink.textContent = error.message;
            loadOlderLink.removeAttribute('aria-disabled');
        }
    });

    newMessagesButton?.addEventListener('click', () => scrollToBottom());
    history?.addEventListener('scroll', () => {
        if (isNearBottom() && newMessagesButton) newMessagesButton.hidden = true;
        scheduleVisibleReadCheck();
    }, { passive: true });

    const highestVisibleIncomingId = () => {
        if (!history || document.hidden) return 0;

        const historyRect = history.getBoundingClientRect();
        const visibleTop = Math.max(historyRect.top, 0);
        const visibleBottom = Math.min(historyRect.bottom, window.innerHeight);
        let highest = 0;

        history.querySelectorAll('[data-message-direction="incoming"][data-message-id]').forEach((row) => {
            const rect = row.getBoundingClientRect();
            if (rect.bottom > visibleTop && rect.top < visibleBottom) {
                highest = Math.max(highest, Number(row.dataset.messageId));
            }
        });

        return highest;
    };

    async function markVisibleMessagesRead() {
        if (!readUrl || readRequestInFlight || document.hidden) return;
        const throughMessageId = highestVisibleIncomingId();
        if (throughMessageId <= lastReadThroughId) return;

        readRequestInFlight = true;
        const requestedId = throughMessageId;

        try {
            const formData = new FormData();
            formData.set('through_message_id', String(requestedId));
            await fetchJson(readUrl, { method: 'POST', body: formData });
            lastReadThroughId = Math.max(lastReadThroughId, requestedId);
            await pollInbox(new AbortController().signal);
        } catch (error) {
            showPollingFailure(error);
        } finally {
            readRequestInFlight = false;
        }
    }

    function scheduleVisibleReadCheck() {
        window.cancelAnimationFrame(visibilityFrame);
        visibilityFrame = window.requestAnimationFrame(markVisibleMessagesRead);
    }

    if (readForm) readForm.hidden = true;
    window.addEventListener('scroll', scheduleVisibleReadCheck, { passive: true });
    window.addEventListener('resize', scheduleVisibleReadCheck, { passive: true });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            window.clearTimeout(pollingTimer);
            pollingController?.abort();
            pollingGeneration++;
        } else {
            pollingDelay = 3000;
            scheduleVisibleReadCheck();
            runPollingCycle();
        }
    });

    window.addEventListener('pagehide', () => {
        window.clearTimeout(pollingTimer);
        pollingController?.abort();
        pollingGeneration++;
    });

    if (history) scrollToBottom('auto');
    scheduleVisibleReadCheck();
    runPollingCycle();
}
