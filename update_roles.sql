-- تحديث نظام الأدوار في قاعدة البيانات broman_db
-- إضافة دور "manager" إلى جدول employees

-- تعديل enum في جدول employees لإضافة دور manager
ALTER TABLE `employees` MODIFY COLUMN `Role` enum('manager','admin','user') NOT NULL DEFAULT 'user';

-- إضافة مستخدم مدير تجريبي (كلمة المرور: manager123)
INSERT INTO `employees` (`FullName`, `Username`, `Password`, `Role`, `DateJoined`) VALUES
('مدير النظام', 'manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', NOW());

-- تحديث المستخدم الحالي admin ليصبح admin بدلاً من admin
-- (لا حاجة لتغيير شيء هنا لأن admin موجود بالفعل)

-- إضافة تعليقات للتوضيح
-- manager: جميع الصلاحيات (إدارة الموظفين + العقارات)
-- admin: إدارة العقارات فقط (إضافة، تعديل، حذف)
-- user: عرض العقارات فقط

