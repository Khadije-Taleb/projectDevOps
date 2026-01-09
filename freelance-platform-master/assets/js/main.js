
document.addEventListener('DOMContentLoaded', function() {
    
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    
    window.formatCurrency = function(amount) {
        return new Intl.NumberFormat('fr-MR', {
            style: 'currency',
            currency: 'MRU',
            minimumFractionDigits: 2
        }).format(amount);
    };
    
    
    window.formatDate = function(dateString, format = 'long') {
        const date = new Date(dateString);
        
        if (format === 'long') {
            return date.toLocaleDateString('fr-FR', {
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });
        } else if (format === 'short') {
            return date.toLocaleDateString('fr-FR', {
                day: 'numeric',
                month: 'numeric',
                year: 'numeric'
            });
        } else if (format === 'datetime') {
            return date.toLocaleDateString('fr-FR', {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } else if (format === 'time') {
            return date.toLocaleTimeString('fr-FR', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        return date.toLocaleDateString('fr-FR');
    };
    
    
    window.truncateText = function(text, length = 100, suffix = '...') {
        if (!text) return '';
        if (text.length <= length) {
            return text;
        }
        return text.substring(0, length) + suffix;
    };
    
    
    window.confirmAction = function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    };
    
    
    window.showAlert = function(type, message, container = 'main.container') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        const containerElement = document.querySelector(container);
        if (containerElement) {
            containerElement.insertBefore(alertDiv, containerElement.firstChild);
            
            
            setTimeout(() => {
                const alert = bootstrap.Alert.getInstance(alertDiv);
                if (alert) {
                    alert.close();
                } else {
                    alertDiv.classList.remove('show');
                    setTimeout(() => alertDiv.remove(), 150);
                }
            }, 5000);
        }
    };
    
    
    window.submitFormAjax = function(form, successCallback, errorCallback) {
        const formData = new FormData(form);
        
        
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) submitButton.disabled = true;
        
        fetch(form.action, {
            method: form.method || 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (successCallback) {
                    successCallback(data);
                } else {
                    showAlert('success', data.message);
                    
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1500);
                    }
                }
            } else {
                if (errorCallback) {
                    errorCallback(data);
                } else {
                    showAlert('danger', data.message);
                }
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showAlert('danger', 'Une erreur est survenue. Veuillez réessayer.');
        })
        .finally(() => {
            
            if (submitButton) submitButton.disabled = false;
        });
    };
    
    
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    
    const passwordFields = document.querySelectorAll('input[type="password"][data-match]');
    Array.from(passwordFields).forEach(field => {
        const matchField = document.getElementById(field.dataset.match);
        if (matchField) {
            field.addEventListener('input', () => {
                if (field.value !== matchField.value) {
                    field.setCustomValidity('Les mots de passe ne correspondent pas.');
                } else {
                    field.setCustomValidity('');
                }
            });
            
            matchField.addEventListener('input', () => {
                if (field.value !== matchField.value) {
                    field.setCustomValidity('Les mots de passe ne correspondent pas.');
                } else {
                    field.setCustomValidity('');
                }
            });
        }
    });
    
    
    const imageInputs = document.querySelectorAll('input[type="file"][data-preview]');
    Array.from(imageInputs).forEach(input => {
        const preview = document.getElementById(input.dataset.preview);
        if (preview) {
            input.addEventListener('change', () => {
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = e => {
                        preview.src = e.target.result;
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            });
        }
    });
    
    
    if (typeof initializeMessaging === 'function') {
        initializeMessaging();
    }
    
    if (typeof initializeFilters === 'function') {
        initializeFilters();
    }
    
    if (typeof initializeCharts === 'function' && typeof Chart !== 'undefined') {
        initializeCharts();
    }
    
    if (typeof initializeRatingStars === 'function') {
        initializeRatingStars();
    }
    
    if (typeof initializeConfirmationModals === 'function') {
        initializeConfirmationModals();
    }
    
    if (typeof initializeCharacterCounters === 'function') {
        initializeCharacterCounters();
    }
    
    if (typeof initializeSkillsFilter === 'function') {
        initializeSkillsFilter();
    }
});

function initializeMessaging() {
    const messagesContainer = document.getElementById('messages-container');
    const messageForm = document.getElementById('message-form');
    const messageInput = document.getElementById('message-input');
    
    if (!messagesContainer || !messageForm || !messageInput) return;
    
    let lastMessageId = 0;
    let isPolling = false;
    let processedMessageIds = new Set(); 
    let lastSentMessageId = null; 
    
    
    const messages = messagesContainer.querySelectorAll('.message');
    if (messages.length > 0) {
        messages.forEach(msg => {
            const msgId = parseInt(msg.dataset.messageId || 0);
            if (msgId > 0) {
                processedMessageIds.add(msgId);
                lastMessageId = Math.max(lastMessageId, msgId);
            }
        });
    }
    
    
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
    
    
    messageForm.addEventListener('submit', function(event) {
        event.preventDefault();
        
        const message = messageInput.value.trim();
        if (!message) return;
        
        const conversationId = messageForm.getAttribute('data-conversation-id');
        
        const formData = new FormData();
        formData.append('id_conversation', conversationId);
        formData.append('contenu', message);
        
        
        const submitButton = messageForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        
        
        messageInput.value = '';
        messageInput.style.height = 'auto';
        
        fetch('../api/messages.php?action=envoyer', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                
                const messageId = data.data.id_message;
                
                if (!processedMessageIds.has(messageId)) {
                    
                    appendMessage(data.data);
                    
                    
                    processedMessageIds.add(messageId);
                    
                    
                    lastMessageId = Math.max(lastMessageId, messageId);
                    
                    
                    lastSentMessageId = messageId;
                }
            } else {
                console.error('Erreur lors de l\'envoi du message:', data.message);
                showAlert('danger', 'Erreur lors de l\'envoi du message: ' + data.message);
                
                
                messageInput.value = message;
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showAlert('danger', 'Une erreur est survenue lors de l\'envoi du message.');
            
            
            messageInput.value = message;
        })
        .finally(() => {
            
            submitButton.disabled = false;
        });
    });
    
    
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
    
    
    messageInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            messageForm.dispatchEvent(new Event('submit'));
        }
    });
    
    
    function appendMessage(message) {
        
        if (document.querySelector(`.message[data-message-id="${message.id_message}"]`)) {
            return; 
        }
        
        const messageElement = document.createElement('div');
        messageElement.className = 'message mb-3 ' + (message.is_sender ? 'message-sent' : 'message-received');
        messageElement.dataset.messageId = message.id_message;
        
        const messageContent = message.contenu ? message.contenu.replace(/\n/g, '<br>') : '';
        
        messageElement.innerHTML = `
            <div class="message-content">
                <div class="message-text">
                    ${messageContent}
                </div>
                <div class="message-info">
                    <small class="text-muted">${formatTime(message.date_envoi)}</small>
                    ${message.is_sender ? '<small class="text-primary ms-1"><i class="fas fa-check"></i></small>' : ''}
                </div>
            </div>
        `;
        
        const messagesDiv = messagesContainer.querySelector('.p-3');
        messagesDiv.appendChild(messageElement);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    
    function formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }
    
    
    function pollNewMessages() {
        if (isPolling || !messageForm) return;
        
        const conversationId = messageForm.getAttribute('data-conversation-id');
        if (!conversationId) return;
        
        isPolling = true;
        
        fetch(`../api/messages.php?action=charger_messages&id_conversation=${conversationId}&last_id=${lastMessageId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    
                    data.data.forEach(message => {
                        
                        
                        if (!processedMessageIds.has(message.id_message) && message.id_message !== lastSentMessageId) {
                            appendMessage(message);
                            processedMessageIds.add(message.id_message);
                            lastMessageId = Math.max(lastMessageId, message.id_message);
                        }
                    });
                    
                    
                    lastSentMessageId = null;
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des messages:', error);
            })
            .finally(() => {
                isPolling = false;
            });
    }
    
    
    
    setTimeout(() => {
        setInterval(pollNewMessages, 5000);
    }, 1000);
}


function initializeFilters() {
    const filterForms = document.querySelectorAll('.filter-form');
    filterForms.forEach(form => {
        const resetButton = form.querySelector('.reset-filters');
        if (resetButton) {
            resetButton.addEventListener('click', function(event) {
                event.preventDefault();
                
                
                form.querySelectorAll('input, select').forEach(input => {
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        input.checked = false;
                    } else if (input.type !== 'submit' && input.type !== 'button') {
                        input.value = '';
                    }
                });
                
                
                form.submit();
            });
        }
    });
}

function initializeCharts() {
    
    if (typeof Chart === 'undefined') return;
    
    
    const chartElements = document.querySelectorAll('[data-chart]');
    chartElements.forEach(element => {
        const chartType = element.dataset.chart;
        const chartData = JSON.parse(element.dataset.chartData || '{}');
        const chartOptions = JSON.parse(element.dataset.chartOptions || '{}');
        
        if (chartType && chartData) {
            new Chart(element, {
                type: chartType,
                data: chartData,
                options: chartOptions
            });
        }
    });
}

function initializeRatingStars() {
    const ratingContainers = document.querySelectorAll('.rating');
    ratingContainers.forEach(container => {
        const stars = container.querySelectorAll('label');
        const input = container.querySelector('input[type="hidden"]');
        
        stars.forEach((star, index) => {
            star.addEventListener('click', () => {
                const rating = 5 - index;
                if (input) input.value = rating;
                
                
                stars.forEach((s, i) => {
                    s.classList.toggle('active', 5 - i <= rating);
                });
            });
        });
    });
}


function initializeConfirmationModals() {
    const confirmButtons = document.querySelectorAll('[data-confirm-modal]');
    confirmButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault();
            
            const modalId = this.dataset.confirmModal;
            const modal = document.getElementById(modalId);
            
            if (modal) {
                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();
            }
        });
    });
}


function initializeCharacterCounters() {
    const textareas = document.querySelectorAll('textarea[data-max-length]');
    textareas.forEach(textarea => {
        const maxLength = parseInt(textarea.dataset.maxLength);
        const counterId = textarea.dataset.counter;
        const counter = document.getElementById(counterId);
        
        if (counter) {
            textarea.addEventListener('input', function() {
                const remaining = maxLength - this.value.length;
                counter.textContent = `${remaining} caractères restants`;
                counter.classList.toggle('text-danger', remaining < 20);
            });
            
            
            textarea.dispatchEvent(new Event('input'));
        }
    });
}

function initializeSkillsFilter() {
    const skillsInput = document.getElementById('skills-filter');
    const skillsList = document.getElementById('skills-list');
    
    if (skillsInput && skillsList) {
        skillsInput.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const skills = skillsList.querySelectorAll('.skill-item');
            
            skills.forEach(skill => {
                const text = skill.textContent.toLowerCase();
                skill.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }
}
