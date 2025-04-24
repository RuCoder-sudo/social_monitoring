let page = 1;
let stop = false;
let allResults = [];

function showTab(tabIndex) {
    $('.tab').removeClass('active');
    $('.tab-buttons button').removeClass('active');
    $('#tab' + tabIndex).addClass('active');
    $('#tabButton' + tabIndex).addClass('active');
}

function logMessage(message) {
    const timestamp = new Date().toLocaleString();
    $('#logs').append('<li>' + timestamp + ' - ' + message + '</li>');
}

function search() {
    if (stop) return;

    logMessage('СТАРТ поиска');
    $('#loadingIndicator').show();

    $.ajax({
        type: 'POST',
        url: 'php/search.php', 
        data: {
            ajax: 1,
            token: $('#token').val(),
            keywords: $('#keywords').val(),
            group_ids: $('#group_ids').val(),
            social_network: $('#social_network').val(),
            page: page,
            time_end: $('input[name="time_end"]').val(),
            time_start: $('input[name="time_start"]').val(),
            ok_token: $('#ok_token').val(),
            ok_public_key: $('#ok_public_key').val(),
            ok_secret_key: $('#ok_secret_key').val()
        },
        success: function(data) {
            try {
                const results = JSON.parse(data);
                if (results.error) {
                    logMessage('Ошибка: ' + results.error);
                } else if (results.length > 0) {
                    const keywords = $('#keywords').val().split(',').map(kw => kw.trim());
                    const highlightColor = $('#highlightColor').val();
                    results.forEach(result => {
                        if (!allResults.some(r => r.link === result.link)) {
                            let highlightedText = result.text;
                            keywords.forEach(keyword => {
                                const regex = new RegExp(`(${keyword})`, 'gi');
                                highlightedText = highlightedText.replace(regex, `<span class="highlight" style="background-color: ${highlightColor};">\$1</span>`);
                            });
                            $('#results').append(`
                                <li>
                                    <img src="${result.avatar}" alt="Avatar" style="width:50px;height:50px;border-radius:50%;">
                                    <p><strong>${result.author}</strong></p>
                                    <p>${highlightedText}</p>
                                    <p><small>${result.date}</small></p>
                                    <p><small>От: ${result.from}</small></p>
                                    <a href="${result.link}" target="_blank">Перейти к сообщению</a>
                                </li>
                            `);
                            allResults.push(result);
                        }
                    });
                    page++;
                    $('#resultsCount').text('Найдено сообщений: ' + allResults.length);
                } else {
                    stop = true;
                    logMessage('Поиск завершен');
                }
            } catch (e) {
                logMessage('Ошибка при обработке данных: ' + e.message);
            }
            $('#loadingIndicator').hide();
            search();
        },
        error: function(xhr, status, error) {
            $('#loadingIndicator').hide();
            logMessage('Ошибка при выполнении запроса: ' + status + ' - ' + error);
            alert('Произошла ошибка при выполнении запроса.');
        }
    });
}

function stopSearch() {
    stop = true;
    $('#loadingIndicator').hide();
    logMessage('Поиск остановлен');
}

function saveResults() {
    const filename = 'results.txt';
    let txtContent = '';
    allResults.forEach(result => {
        txtContent += `Текст: ${result.text}\nСсылка: ${result.link}\n\n`;
    });
    const blob = new Blob([txtContent], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function resetForm() {
    $('#searchForm')[0].reset();
    $('#results').empty();
    $('#resultsCount').text('Найдено сообщений: 0');
    page = 1;
    stop = true;
    allResults = [];
    $('#loadingIndicator').hide();
    logMessage('Форма сброшена');
}

$(document).ready(function() {
    showTab(1);

    $('#searchForm').submit(function(e) {
        e.preventDefault();
        $('#results').empty();
        $('#resultsCount').text('Найдено сообщений: 0');
        page = 1;
        stop = false;
        allResults = [];
        search();
    });

    $('#saveButton').click(function() {
        saveResults();
    });

    $('#resetButton').click(function() {
        resetForm();
    });

    $('#social_network').change(function() {
        if ($(this).val() === 'telegram') {
            $('body').addClass('telegram-theme');
        } else {
            $('body').removeClass('telegram-theme');
        }
        
        // Показываем/скрываем поля для Одноклассников
        if ($(this).val() === 'ok') {
            $('#okFields').show();
        } else {
            $('#okFields').hide();
        }
    });

    // Добавляем выбор цвета выделения ключевых слов
    $('#highlightColor').change(function() {
        const color = $(this).val();
        $('.highlight').css('background-color', color);
    });

    // Добавляем обработчик для кнопки "Очистить логи"
    $('#clearLogs').click(function() {
        console.log('Очистка логов начата'); // Добавлено логирование
        fetch('php/clear_logs.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'ajax=true&clear_logs=true'
        })
        .then(response => response.json())
        .then(data => {
            console.log('Ответ сервера:', data); // Добавлено логирование
            if (data.status === 'logs_cleared') {
                alert('Логи успешно очищены');
                $('#logs').empty(); // Очистка списка логов на странице
            } else {
                alert('Ошибка: ' + data.status);
            }
        })
        .catch(error => {
            console.error('Ошибка при очистке логов:', error);
        });
    });
});
