document.addEventListener('DOMContentLoaded', function() {
    // تحديد كل من صفوف الجدول وبطاقات العرض لجعلها قابلة للنقر
    const clickableElements = document.querySelectorAll('.property-row, .property-card');

    clickableElements.forEach(function(element) {
        // تغيير شكل المؤشر للإشارة إلى أن العنصر قابل للنقر
        element.style.cursor = 'pointer';

        // إضافة حدث النقر
        element.addEventListener('click', function(event) {
            // لا تقم بالتوجيه إذا كان النقر على زر أو رابط
            if (event.target.closest('a, button')) {
                return;
            }

            // إذا لم يكن النقر على زر، انتقل إلى صفحة تفاصيل العقار
            const propertyId = this.dataset.propertyId; // استخدام .dataset أفضل للوصول لـ data-*
            if (propertyId) {
                window.location.href = 'view_property.php?id=' + propertyId;
            }
        });

        // --- تطبيق تأثيرات التمرير على صفوف الجدول فقط ---
        if (element.classList.contains('property-row')) {
            element.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8f9fa';
                this.style.transform = 'translateY(-1px)';
                this.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
            });

            element.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
                this.style.transform = '';
                this.style.boxShadow = '';
            });
        }
    });
});