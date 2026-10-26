// Server-side providers: fixed destinations, bounded requests, cache, no browser CORS dependency.
export function createLocationService(fetcher = fetch, clock = Date.now) {
  const cache = new Map();
  let searchBusy = false, lastSearch = -Infinity;
  async function json(url, ttl) {
    const cached = cache.get(url);
    if (cached && cached.until > clock()) return cached.value;
    const response = await fetcher(url, {
      headers: { 'User-Agent': 'StudioAttendance/1.0 (studio address configuration)', 'Accept': 'application/json' },
      signal: AbortSignal.timeout(10000), redirect: 'error'
    });
    if (!response.ok) throw new Error('Dịch vụ địa chỉ đang bận. Vui lòng thử lại hoặc chọn ghim trực tiếp trên bản đồ.');
    const value = await response.json();
    if (cache.size > 200) cache.clear();
    cache.set(url, { value, until: clock() + ttl });
    return value;
  }
  const units = values => {
    if (!Array.isArray(values)) throw new Error('Dữ liệu hành chính không hợp lệ.');
    return values.map(v => {
      if (!Number.isInteger(Number(v.code)) || typeof v.name !== 'string') throw new Error('Dữ liệu hành chính không hợp lệ.');
      return { code: String(v.code), name: v.name };
    }).sort((a,b)=>a.name.localeCompare(b.name,'vi'));
  };
  return {
    async provinces() { return units(await json('https://provinces.open-api.vn/api/v2/?depth=1', 86400000)); },
    async wards(province) {
      if (!/^\d{1,2}$/.test(province)) throw new Error('Mã tỉnh/thành không hợp lệ.');
      const result = await json(`https://provinces.open-api.vn/api/v2/p/${Number(province)}?depth=2`, 86400000);
      return units(result.wards);
    },
    async search(query) {
      if (typeof query !== 'string' || query.trim().length < 5 || query.length > 250) throw new Error('Nhập địa chỉ studio từ 5–250 ký tự.');
      const url = 'https://nominatim.openstreetmap.org/search?' + new URLSearchParams({ q: query.trim(), format: 'jsonv2', countrycodes: 'vn', limit: '5', 'accept-language': 'vi' });
      const existing = cache.get(url);
      if (existing && existing.until > clock()) return existing.value;
      if (searchBusy || clock() - lastSearch < 1100) throw new Error('Vui lòng đợi một chút trước khi tìm địa chỉ tiếp theo.');
      searchBusy = true;lastSearch = clock();
      try {
        const response = await fetcher(url, { headers: { 'User-Agent': 'StudioAttendance/1.0 (studio address configuration)', 'Accept': 'application/json' }, signal: AbortSignal.timeout(10000), redirect: 'error' });
        if (!response.ok) throw new Error('Dịch vụ tìm địa chỉ đang bận. Bạn có thể chọn ghim trực tiếp trên bản đồ.');
        const data = await response.json();
        if (!Array.isArray(data)) throw new Error('Kết quả địa chỉ không hợp lệ.');
        const results = data.map(v=>({label:String(v.display_name),latitude:Number(v.lat),longitude:Number(v.lon)})).filter(v=>Number.isFinite(v.latitude)&&Number.isFinite(v.longitude)&&Math.abs(v.latitude)<=90&&Math.abs(v.longitude)<=180);
        if (cache.size>200) cache.clear();
        cache.set(url,{value:results,until:clock()+3600000});
        return results;
      } finally { searchBusy=false; }
    }
  };
}
