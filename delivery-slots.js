jQuery(document).ready(function($) {
    class DeliveryCalendar {
      constructor() {
        this.currentDate = new Date();
        this.calendarEl = $('#delivery-calendar');
        this.monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                         'July', 'August', 'September', 'October', 'November', 'December'];
        
        // Parse backend slots
        this.slotsByDate = deliverySlotsData.slots || {};
        
        this.init();
      }
      
      init() {
        this.render();
        this.bindEvents();
      }
      
      render() {
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        
        // Calendar header
        this.calendarEl.html(`
          <div class="calendar-header">
            <button class="prev-month">‹ PREV</button>
            <h2>${this.monthNames[month]} ${year}</h2>
            <button class="next-month">NEXT ›</button>
          </div>
          <div class="weekdays">
            <div>Sunday</div>
            <div>Monday</div>
            <div>Tuesday</div>
            <div>Wednesday</div>
            <div>Thursday</div>
            <div>Friday</div>
            <div>Saturday</div>
          </div>
          <div class="calendar-days"></div>
        `);
        
        const daysContainer = this.calendarEl.find('.calendar-days');
        
        // Get first day of month and total days
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const totalDays = lastDay.getDate();
        const firstDayIndex = firstDay.getDay();
        
        // Get today's date for highlighting
        const today = new Date();
        
        // Add days from previous month
        const prevMonthLastDay = new Date(year, month, 0).getDate();
        for (let i = firstDayIndex - 1; i >= 0; i--) {
          daysContainer.append(this.createDayElement(prevMonthLastDay - i, 'other-month'));
        }
        
        // Add days of current month
        for (let day = 1; day <= totalDays; day++) {
          const date = new Date(year, month, day);
          const dateStr = this.formatDate(date);
          const slots = this.slotsByDate[dateStr] || [];
          const isToday = day === today.getDate() && 
                         month === today.getMonth() && 
                         year === today.getFullYear();
          
          daysContainer.append(this.createDayElement(
            day, 
            isToday ? 'today' : '',
            slots
          ));
        }
        
        // Add days from next month
        const remainingDays = 42 - (firstDayIndex + totalDays);
        for (let day = 1; day <= remainingDays; day++) {
          daysContainer.append(this.createDayElement(day, 'other-month'));
        }
      }
      
      createDayElement(day, className = '', slots = []) {
        const dayEl = $(`<div class="calendar-day ${className} ${slots.length ? 'has-slots' : ''}"></div>`);
        dayEl.append(`<div class="day-number">${day}</div>`);
        
        if (slots.length > 0) {
          const slotsContainer = $('<div class="time-slots"></div>');
          
          slots.forEach(slot => {
            slotsContainer.append(`
              <div class="time-slot" data-start="${slot.start}" data-end="${slot.end}">
                ${slot.display}
              </div>
            `);
          });
          
          dayEl.append(slotsContainer);
        }
        
        return dayEl;
      }
      
      formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      }
      
      bindEvents() {
        // Month navigation
        this.calendarEl.on('click', '.prev-month', () => {
          this.currentDate.setMonth(this.currentDate.getMonth() - 1);
          this.render();
        });
        
        this.calendarEl.on('click', '.next-month', () => {
          this.currentDate.setMonth(this.currentDate.getMonth() + 1);
          this.render();
        });
        
        // Slot selection
        this.calendarEl.on('click', '.time-slot', function() {
          const dayEl = $(this).closest('.calendar-day');
          const day = dayEl.find('.day-number').text();
          const month = $('.calendar-header h2').text().split(' ')[0];
          const year = $('.calendar-header h2').text().split(' ')[1];
          const start = $(this).data('start');
          const end = $(this).data('end');
          
          // Format the selected slot
          const selectedSlot = `${day} ${month} ${year}, ${start} - ${end}`;
          
          // Update hidden field
          $('input[name="delivery_slot"]').val(selectedSlot);
          
          // Visual feedback
          $('.time-slot').removeClass('selected');
          $(this).addClass('selected');
          
          // Update the display immediately
          $('#selected-delivery-slot').remove();
          $('#delivery-slot-selection').append(`
            <div id="selected-delivery-slot">
              <strong>Selected Delivery Slot:</strong> ${selectedSlot}
            </div>
          `);
        });
      }
    }
    
    // Initialize calendar
    if ($('#delivery-calendar').length) {
      new DeliveryCalendar();
    }
  });
