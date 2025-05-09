jQuery(document).ready(function($) {
    console.log("Delivery Slots Admin loaded");
    
    class AdminDeliveryCalendar {
        constructor() {
            this.currentDate = new Date();
            this.monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];
            
            this.initElements();
            this.init();
        }

        initElements() {
            this.calendarEl = $('#admin-delivery-calendar');
            this.modal = $('#timeSlotModal');
            this.modalDateTitle = $('#modalDate');
            this.timeSlotsContainer = $('#timeSlotsContainer');
            this.currentSelectedDate = null;
        }

        init() {
            this.render();
            this.bindEvents();
        }

        getSlotsForDate(date) {
            const dateStr = this.formatDateForStorage(date);
            return deliverySlotsData.saved_slots[dateStr] || [];
        }

        formatDateForStorage(date) {
            const pad = num => String(num).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        }

        formatDateForDisplay(date) {
            return `${this.monthNames[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
        }

        render() {
            const year = this.currentDate.getFullYear();
            const month = this.currentDate.getMonth();
            
            this.calendarEl.html(`
                <div class="calendar-header">
                    <button class="prev-month">‹ PREV</button>
                    <h2>${this.monthNames[month]} ${year}</h2>
                    <button class="next-month">NEXT ›</button>
                </div>
                <div class="weekdays">
                    <div>Sun</div><div>Mon</div><div>Tue</div>
                    <div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
                </div>
                <div class="calendar-days"></div>
            `);
            
            this.renderCalendarDays(year, month);
        }

        renderCalendarDays(year, month) {
            const daysContainer = this.calendarEl.find('.calendar-days');
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const prevMonthDays = new Date(year, month, 0).getDate();

            // Previous month days
            for (let i = 0; i < firstDay; i++) {
                daysContainer.append(this.createDayElement(prevMonthDays - i, 'other-month'));
            }

            // Current month days
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(year, month, day);
                const slots = this.getSlotsForDate(date);
                const dayEl = this.createDayElement(day, '', slots);
                
                // Add data attribute for easy selection later
                dayEl.attr('data-date', this.formatDateForStorage(date));
                daysContainer.append(dayEl);
            }

            // Next month days
            const remainingDays = 42 - (firstDay + daysInMonth);
            for (let day = 1; day <= remainingDays; day++) {
                daysContainer.append(this.createDayElement(day, 'other-month'));
            }
        }

        createDayElement(day, className = '', slots = []) {
            const dayEl = $(`<div class="calendar-day ${className} ${slots.length ? 'has-slots' : ''}"></div>`);
            dayEl.append(`<div class="day-number">${day}</div>`);
            
            if (!className.includes('other-month')) {
                dayEl.addClass('selectable');
                if (slots.length) {
                    const slotsContainer = $('<div class="time-slots"></div>');
                    
                    slots.forEach(slot => {
                        slotsContainer.append(`
                            <div class="time-slot">${slot.start} - ${slot.end}</div>
                        `);
                    });
                    
                    dayEl.append(slotsContainer);
                }
            }
            
            return dayEl;
        }

        bindEvents() {
            this.bindMonthNavigation();
            this.bindDaySelection();
            this.bindModalEvents();
        }

        bindMonthNavigation() {
            this.calendarEl.on('click', '.prev-month', () => {
                this.currentDate.setMonth(this.currentDate.getMonth() - 1);
                this.render();
            });
            
            this.calendarEl.on('click', '.next-month', () => {
                this.currentDate.setMonth(this.currentDate.getMonth() + 1);
                this.render();
            });
        }

        bindDaySelection() {
            this.calendarEl.on('click', '.calendar-day.selectable', (e) => {
                const day = $(e.currentTarget).find('.day-number').text();
                const date = new Date(
                    this.currentDate.getFullYear(),
                    this.currentDate.getMonth(),
                    day
                );
                this.currentSelectedDate = date;
                this.showTimeSlotModal(date);
            });
        }

        bindModalEvents() {
            $('.close').on('click', () => this.modal.hide());
            
            $('#addNewSlotBtn').on('click', () => {
                const start = $('#newSlotStart').val();
                const end = $('#newSlotEnd').val();
                if (start && end) {
                    this.addTimeSlotToModal(start, end);
                    $('#newSlotStart, #newSlotEnd').val('');
                }
            });
            
            $('#saveTimeSlotsBtn').on('click', () => this.saveTimeSlots());
        }

        showTimeSlotModal(date) {
            this.timeSlotsContainer.empty();
            this.modalDateTitle.text(this.formatDateForDisplay(date));
            
            this.getSlotsForDate(date).forEach(slot => {
                this.addTimeSlotToModal(slot.start, slot.end);
            });
            
            this.modal.show();
        }

        addTimeSlotToModal(start, end) {
            const slotId = 'slot-' + Date.now();
            this.timeSlotsContainer.append(`
                <div class="time-slot-input" data-id="${slotId}">
                    <input type="time" value="${start}" class="slot-start">
                    <span>to</span>
                    <input type="time" value="${end}" class="slot-end">
                    <button class="button remove-slot">Remove</button>
                </div>
            `);
            
            $(`[data-id="${slotId}"] .remove-slot`).on('click', (e) => {
                $(e.currentTarget).parent().remove();
            });
        }

        saveTimeSlots() {
            const slots = [];
            
            this.timeSlotsContainer.find('.time-slot-input').each(function() {
                slots.push({
                    start: $(this).find('.slot-start').val(),
                    end: $(this).find('.slot-end').val(),
                    display: $(this).find('.slot-start').val() + ' - ' + $(this).find('.slot-end').val()
                });
            });
        
            $.ajax({
                url: deliverySlotsData.ajax_url,
                type: 'POST',
                data: {
                    action: 'save_delivery_slots',
                    date: this.formatDateForStorage(this.currentSelectedDate),
                    slots: slots
                },
                success: (response) => {
                    if (response.success) {
                        // Update the local data immediately
                        const dateStr = this.formatDateForStorage(this.currentSelectedDate);
                        deliverySlotsData.saved_slots[dateStr] = slots;
                        
                        // Find the day element using the data-date attribute
                        const dayElement = $(`.calendar-day[data-date="${dateStr}"]`);
                        
                        if (dayElement.length) {
                            // Clear existing content except day number
                            const dayNumber = dayElement.find('.day-number').detach();
                            dayElement.empty().append(dayNumber);
                            
                            // Update slots display
                            if (slots.length > 0) {
                                dayElement.addClass('has-slots');
                                const slotsContainer = $('<div class="time-slots"></div>');
                                
                                slots.forEach(slot => {
                                    slotsContainer.append(`
                                        <div class="time-slot">
                                            ${slot.start} - ${slot.end}
                                        </div>
                                    `);
                                });
                                
                                dayElement.append(slotsContainer);
                            } else {
                                dayElement.removeClass('has-slots');
                            }
                            
                            // Visual feedback - briefly highlight the updated day
                            dayElement.css('background-color', '#fcebcc');
                            setTimeout(() => {
                                dayElement.css('background-color', '');
                            }, 1000);
                        } else {
                            console.error('Could not find calendar day element for date:', dateStr);
                        }
                        
                        this.modal.hide();
                    }
                }
            });
        }
        
        updateCalendarDay(date, slots) {
            const dateStr = this.formatDateForStorage(date);
            const dayElement = $(`.calendar-day[data-date="${dateStr}"]`);
            
            if (dayElement.length) {
                // Clear existing content except day number
                const dayNumber = dayElement.find('.day-number').detach();
                dayElement.empty().append(dayNumber);
                
                // Update slots display
                if (slots && slots.length > 0) {
                    dayElement.addClass('has-slots');
                    const slotsContainer = $('<div class="time-slots"></div>');
                    
                    slots.forEach(slot => {
                        slotsContainer.append(`
                            <div class="time-slot">
                                ${slot.start} - ${slot.end}
                            </div>
                        `);
                    });
                    
                    dayElement.append(slotsContainer);
                } else {
                    dayElement.removeClass('has-slots');
                }
            }
        }
    }
    
    // Initialize calendar
    new AdminDeliveryCalendar();
});
