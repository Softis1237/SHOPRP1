/**
 * JavaScript для страницы входа/регистрации.
 * Обрабатывает выбор гендера, расы и валидацию на стороне клиента.
 * Файл: assets/js/login-register.js
 *
 * Изменения: В основном без изменений, так как не делает прямых AJAX-вызовов для регистрации.
 * Убедитесь, что HTML-структура, с которой он взаимодействует (флип-боксы, скрытые поля),
 * осталась совместимой после рефакторинга шорткодов и HTML-блока Elementor.
 */
document.addEventListener("DOMContentLoaded", function() {
    // Функция для инициализации кастомных скриптов (гендер, флип-боксы, валидация)
    function initializeCustomScripts() {
        const genderRadios = document.querySelectorAll(".gender-option input[type=\"radio\"]");
        const flipBoxes = document.querySelectorAll(".custom-flip-box"); // Класс ваших флип-боксов для рас
        const registerFormElement = document.querySelector("form.woocommerce-form-register"); // Более точный селектор

        // Скрытые поля, которые обновляет этот скрипт
        const selectedGenderField = document.querySelector("input[name=\"selected_gender\"]");
        const selectedRaceField = document.querySelector("input[name=\"selected_race\"]");

        // Функция для обновления значения гендера
        function updateGender(selectedValue) {
            if (selectedGenderField) {
                selectedGenderField.value = selectedValue;
                // console.log('Selected gender updated:', selectedValue);
            } else {
                console.error("RPG Script Error: Hidden field 'selected_gender' not found.");
            }
        }

        // Функция для сброса выбора расы
        function resetRaceSelection() {
            flipBoxes.forEach(function(box) {
                box.classList.remove("elementor-flip-box--flipped"); // Класс Elementor для перевернутого состояния
            });

            if (selectedRaceField) {
                selectedRaceField.value = "";
                // console.log('Race selection reset.');
            } else {
                console.error("RPG Script Error: Hidden field 'selected_race' not found.");
            }
        }

        // Устанавливаем начальный гендер (если радио-кнопки уже есть на странице)
        const initiallyCheckedGender = document.querySelector(".gender-option input[type=\"radio\"]:checked");
        if (initiallyCheckedGender) {
            updateGender(initiallyCheckedGender.value);
        } else if (genderRadios.length > 0) {
            // Если ничего не выбрано, можно установить значение по умолчанию, например, 'male'
            // genderRadios[0].checked = true; // Раскомментируйте, если нужно установить по умолчанию
            // updateGender(genderRadios[0].value);
        }


        const maleContainer = document.querySelector("#flip-boxes-male"); // ID контейнера мужских рас
        const femaleContainer = document.querySelector("#flip-boxes-female"); // ID контейнера женских рас

        function toggleRaceContainers(selectedGenderValue) {
            if (maleContainer && femaleContainer) {
                if (selectedGenderValue === "male") {
                    maleContainer.style.display = "block";
                    femaleContainer.style.display = "none";
                } else if (selectedGenderValue === "female") {
                    maleContainer.style.display = "none";
                    femaleContainer.style.display = "block";
                } else { // Если пол не выбран или значение некорректно, можно скрыть оба
                    maleContainer.style.display = "none";
                    femaleContainer.style.display = "none";
                }
            }
        }

        // Инициализация контейнеров рас при загрузке
        if (initiallyCheckedGender) {
            toggleRaceContainers(initiallyCheckedGender.value);
        } else if (selectedGenderField && selectedGenderField.value) {
             toggleRaceContainers(selectedGenderField.value);
        } else {
            // Если нет выбранного гендера, можно показать мужской по умолчанию или оба/ни одного
            if (maleContainer) maleContainer.style.display = "block"; // По умолчанию показываем мужские
            if (femaleContainer) femaleContainer.style.display = "none";
        }


        // Обработчик выбора гендера
        genderRadios.forEach(function(radio) {
            radio.addEventListener("change", function() {
                if (this.checked) {
                    updateGender(this.value);
                    resetRaceSelection(); // Сбрасываем выбор расы при смене пола
                    toggleRaceContainers(this.value);
                }
            });
        });

        // Обработчик флип-боксов
        flipBoxes.forEach(function(box) {
            // Убираем класс переворота при инициализации, если он был установлен
            box.classList.remove("elementor-flip-box--flipped");

            box.addEventListener("click", function() {
                // Сбрасываем состояние всех флип-боксов в активном контейнере
                const activeContainer = maleContainer.style.display === "block" ? maleContainer : femaleContainer;
                if (activeContainer) {
                    activeContainer.querySelectorAll(".custom-flip-box").forEach(function(otherBox) {
                        if (otherBox !== box) {
                            otherBox.classList.remove("elementor-flip-box--flipped");
                        }
                    });
                }


                // Переворачиваем текущий флип-бокс
                this.classList.toggle("elementor-flip-box--flipped"); // Toggle для возможности отмены выбора кликом

                let race = "";
                if (this.classList.contains("elementor-flip-box--flipped")) { // Только если бокс выбран
                    // Определяем выбранную расу по специфичному классу флип-бокса
                    if (this.classList.contains("flip-box-orc")) race = "orc";
                    else if (this.classList.contains("flip-box-elf")) race = "elf";
                    else if (this.classList.contains("flip-box-human")) race = "human";
                    else if (this.classList.contains("flip-box-dwarf")) race = "dwarf";
                    // Добавьте другие расы, если есть
                }

                if (selectedRaceField) {
                    selectedRaceField.value = race;
                    // console.log('Selected race updated:', race);
                } else {
                    console.error("RPG Script Error: Hidden field 'selected_race' not found.");
                }
            });
        });

        // Валидация формы перед отправкой
        if (registerFormElement) {
            registerFormElement.addEventListener("submit", function(e) {
                const currentSelectedGender = selectedGenderField ? selectedGenderField.value : '';
                const currentSelectedRace = selectedRaceField ? selectedRaceField.value : '';

                // Удаляем старые сообщения об ошибке
                const existingErrors = registerFormElement.querySelectorAll(".rpg-validation-error");
                existingErrors.forEach(error => error.remove());

                let hasError = false;
                let firstErrorElement = null;

                // Проверка гендера
                if (!currentSelectedGender) {
                    hasError = true;
                    const genderError = document.createElement("p");
                    genderError.className = "woocommerce-error rpg-validation-error"; // Используем класс WooCommerce для стилей
                    genderError.setAttribute('role', 'alert');
                    genderError.textContent = rpg_settings && rpg_settings.text && rpg_settings.text.error_select_gender ? rpg_settings.text.error_select_gender : "Пожалуйста, выберите гендер."; // Локализованный текст
                    const genderSelectDiv = document.querySelector(".gender-select");
                    if (genderSelectDiv) {
                        genderSelectDiv.parentNode.insertBefore(genderError, genderSelectDiv.nextSibling);
                        if (!firstErrorElement) firstErrorElement = genderSelectDiv;
                    }
                }

                // Проверка расы
                if (!currentSelectedRace) {
                    hasError = true;
                    const raceError = document.createElement("p");
                    raceError.className = "woocommerce-error rpg-validation-error";
                    raceError.setAttribute('role', 'alert');
                    raceError.textContent = rpg_settings && rpg_settings.text && rpg_settings.text.error_select_race ? rpg_settings.text.error_select_race : "Пожалуйста, выберите расу."; // Локализованный текст
                    
                    const flipBoxesContainer = maleContainer && maleContainer.style.display === "block" ? maleContainer : femaleContainer;
                    if (flipBoxesContainer) {
                        flipBoxesContainer.parentNode.insertBefore(raceError, flipBoxesContainer.nextSibling);
                        if (!firstErrorElement) firstErrorElement = flipBoxesContainer;
                    }
                }

                // Если есть ошибки, предотвращаем отправку формы и фокусируемся на первом проблемном элементе
                if (hasError) {
                    e.preventDefault();
                    if (firstErrorElement) {
                        firstErrorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });
        }
    }

    // Инициализируем скрипты, если форма регистрации видима (например, через класс на body или контейнере)
    // Ваш код использует .wd-registration-page.active-register
    const registrationPageContainer = document.querySelector(".wd-registration-page");

    if (registrationPageContainer) {
        // Проверяем начальное состояние
        if (registrationPageContainer.classList.contains("active-register")) {
            initializeCustomScripts();
        }

        // Наблюдаем за изменением класса active-register
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === "class") {
                    if (registrationPageContainer.classList.contains("active-register")) {
                        initializeCustomScripts();
                        // Инициализируем Elementor, если он есть и если это необходимо после динамического показа блока
                        if (typeof elementorFrontend !== 'undefined' && typeof elementorFrontend.hooks !== 'undefined') {
                             // Для Elementor > 2.8
                            elementorFrontend.hooks.doAction('frontend/element_ready/widget', jQuery(registrationPageContainer).find('.elementor-widget'), jQuery);
                        } else if (typeof elementorFrontend !== 'undefined') {
                            // Для старых версий Elementor
                            elementorFrontend.init();
                        }
                    }
                }
            });
        });
        observer.observe(registrationPageContainer, { attributes: true });
    } else {
        // Если нет .wd-registration-page, но форма может быть на странице (например, шорткод напрямую)
        if (document.querySelector("form.woocommerce-form-register")) {
            initializeCustomScripts();
        }
    }
});