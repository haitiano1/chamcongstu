export const PROFILE_FIELDS=['phone','department','job_title','start_date','birth_date','address','bank_name','bank_account','bank_holder','notes'];
export function validateEmployee(input,{create=false}={}){
 const b={...input};
 if(typeof b.name!=='string'||!b.name.trim()||b.name.trim().length>80)throw new Error('Họ tên phải từ 1–80 ký tự.');
 if(typeof b.email!=='string'||b.email.length>254||!/^\S+@\S+\.\S+$/.test(b.email.trim()))throw new Error('Email không hợp lệ.');
 b.name=b.name.trim();b.email=b.email.trim().toLowerCase();
 if(!['admin','manager','employee'].includes(b.role))throw new Error('Vai trò không hợp lệ.');
 if(!['active','probation','inactive'].includes(b.status))throw new Error('Trạng thái làm việc không hợp lệ.');
 if(!Number.isSafeInteger(b.monthly_salary)||b.monthly_salary<0||b.monthly_salary>1000000000)throw new Error('Lương cứng phải là số nguyên từ 0–1 tỷ đồng.');
 if(create||b.password){if(typeof b.password!=='string'||b.password.length<10||b.password.length>128)throw new Error('Mật khẩu cần từ 10–128 ký tự.');}
 for(const f of PROFILE_FIELDS){if(b[f]===undefined)b[f]='';if(typeof b[f]!=='string'||b[f].length>(f==='notes'?2000:f==='address'?500:150))throw new Error('Thông tin hồ sơ quá dài hoặc không hợp lệ.');b[f]=b[f].trim();}
 if(b.phone&&!/^[+\d\s().-]{7,25}$/.test(b.phone))throw new Error('Số điện thoại không hợp lệ.');
 if(b.bank_account&&!/^[A-Za-z0-9 -]{3,50}$/.test(b.bank_account))throw new Error('Số tài khoản ngân hàng không hợp lệ.');
 for(const f of ['start_date','birth_date'])if(b[f]){if(!/^\d{4}-\d{2}-\d{2}$/.test(b[f])||!Number.isFinite(Date.parse(b[f]))||new Date(b[f]+'T00:00:00Z').toISOString().slice(0,10)!==b[f])throw new Error('Ngày trong hồ sơ không hợp lệ.');}
 return b;
}
export function authorizeEmployeeEdit(actor,target,change){
 if(actor.role!=='admin'&&actor.role!=='manager')throw new Error('Bạn không có quyền quản lý nhân viên.');
 if(actor.role==='manager'&&(target?.role&&target.role!=='employee'||change?.role&&change.role!=='employee'))throw new Error('Quản lý chỉ được quản lý tài khoản Nhân viên.');
 if(target?.id===actor.id&&(change?.role&&change.role!==actor.role||change?.status==='inactive'||change?.archived))throw new Error('Bạn không thể tự hạ quyền, khóa hoặc xóa tài khoản đang sử dụng.');
}
