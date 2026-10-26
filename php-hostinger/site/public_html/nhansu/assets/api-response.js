// Do not display raw HTML or server debug responses: they may contain private details.
export async function readApiResponse(response){
 const raw=await response.text();
 const suffix=`HTTP ${response.status}`;
 if(!raw.trim())throw new Error(`Máy chủ trả về nội dung trống (${suffix}). Mở trang kiemtra-capnhat.php để kiểm tra file cập nhật và PHP.`);
 let data;
 try{data=JSON.parse(raw);}catch{throw new Error(`Máy chủ chưa trả dữ liệu JSON hợp lệ (${suffix}). Kiểm tra kiemtra-capnhat.php; nếu hiện trang WordPress hoặc thông báo chặn, kiểm tra đường dẫn /nhansu trên hosting.`);}
 if(!data||typeof data!=='object'||Array.isArray(data))throw new Error(`Phản hồi máy chủ không đúng định dạng (${suffix}). Hãy tải lại trang sau khi cập nhật đủ mã nguồn.`);
 if(!response.ok){const message=typeof data.error==='string'&&data.error?data.error:`Máy chủ gặp lỗi (${suffix}).`;throw new Error(message+(data.reference?` Mã tham chiếu: ${data.reference}.`:''));}
 return data;
}
