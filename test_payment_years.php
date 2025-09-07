<?php
session_start();
require_once __DIR__ . '/lang/translate.php';
require_once __DIR__ . '/db.php';

// تعريف دالة formatPaymentTerms للاختبار
function formatPaymentTerms($years)
{
    // تحويل القيمة إلى عدد عشري
    $years = floatval($years);
    
    // تحديد عدد الخانات العشرية بناءً على وجود كسور
    $decimal_places = fmod($years, 1) !== 0.0 ? 1 : 0;
    
    // تنسيق الرقم مع إظهار الكسور إذا وجدت
    $years_formatted = number_format($years, $decimal_places, '.', ',');
    
    // تحديد كلمة "سنة" أو "سنوات" بناءً على القيمة
    // استخدام القيمة 1 بالضبط للمفرد، وأي قيمة أخرى للجمع
    // بما في ذلك القيم العشرية مثل 1.5 تستخدم صيغة الجمع "سنوات"
    $year_word = ($years == 1) ? 'سنة' : 'سنوات';
    
    return $years_formatted . ' ' . $year_word;
}

// قيم للاختبار
$test_values = [1, 2, 1.5, 0.5, 5, 10];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار عرض فترة السداد</title>
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="modern_styles.css">
    <link rel="stylesheet" href="rtl_styles.css">
    <style>
        .test-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-item {
            margin-bottom: 15px;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>اختبار عرض فترة السداد</h1>
        
        <?php foreach ($test_values as $value): ?>
            <div class="test-item">
                <strong>القيمة الأصلية:</strong> <?php echo $value; ?><br>
                <strong>النتيجة:</strong> <?php echo formatPaymentTerms($value); ?>
            </div>
        <?php endforeach; ?>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="index.php" class="btn btn-primary">العودة للصفحة الرئيسية</a>
        </div>
    </div>
</body>
</html>