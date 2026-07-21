# Rule tạo block Gutenberg dùng chung nhiều site (lacadev-client-child)

Đọc file này trước khi tạo block mới trong `block-gutenberg/`. Các block ở đây
được build 1 lần, dùng lại cho nhiều site khách khác nhau (qua Block
Marketplace sync), nên phải tuân thủ đúng các quy tắc dưới đây để không phải
sửa lại về sau.

## 1. Đặt tên — luôn generic, không gắn với 1 ngành/1 khách hàng cụ thể

Sai: `Dental Services Block`, `Clinic Stats Block`, `Doctor Team Block`
(gắn cứng với 1 ngành — nha khoa). Đúng: `Feature Cards Block`,
`Stats Cards Block`, `Team Members Block` — mô tả ĐÚNG HÌNH DẠNG/CHỨC NĂNG
hiển thị (cards, grid, stats…), không mô tả nội dung cụ thể của 1 khách hàng.

Áp dụng nhất quán ở 4 chỗ trong `block.json` + đúng handle ở 2 chỗ:
- `name` (vd `lacadev/feature-cards-block`)
- `title`, `description`, `keywords` — không nhắc tên ngành cụ thể
- `editorScript`/`style` — LUÔN theo đúng tên **thư mục** hiện tại của block:
  `block-block-{ten-thu-muc}-editor` / `block-block-{ten-thu-muc}`
  (do `lacadev_child_register_synced_blocks()` tự suy ra handle từ tên thư
  mục — đổi tên thư mục mà quên đổi 2 dòng này sẽ làm block mất script/style).
- `ServerSideRender`'s `block` prop trong `edit.js` — PHẢI khớp chính xác
  `block.json`'s `name`, nếu không preview trong editor sẽ trắng/lỗi.

Nếu đổi `name` của 1 block ĐÃ được chèn vào trang thật (đã có nội dung), các
instance cũ sẽ thành "block không nhận dạng được" — cần xoá và chèn lại theo
tên mới, KHÔNG tự sửa ngầm.

## 2. Block thiết kế riêng cho 1 site phải gán `category` = site đó

Kho `block-gutenberg/` này chứa thiết kế của NHIỀU site khác nhau cùng lúc
(site dev/tham chiếu clients.lacadev.com dùng chung 1 thư mục cho tất cả).
Nhiều block dễ trùng Ý NGHĨA (hero banner, stats, team…) nhưng khác hẳn giao
diện giữa các site — nếu không phân loại, khi cần tìm "bộ block đã làm cho
site X" để sync/tham khảo sẽ rất khó vì tất cả gộp chung 1 danh sách phẳng.

Quy tắc: mỗi block được thiết kế riêng cho 1 site set `"category"` trong
`block.json` thành `site-{slug-site}` (vd `site-nhakhoathienphuoc`), thay vì
category chung `lacadev-blocks`. Category này hiện ngay trong ô tìm kiếm của
trình chèn block (Gutenberg inserter) — gõ tên site là lọc ra đúng bộ block
của site đó.

Trước khi dùng 1 slug site mới, đăng ký category tương ứng trong
`lacadev_get_custom_block_categories()`
(`lacadev-client/theme/setup/gutenberg-blocks.php`, mảng
`$site_categories`) — thiếu bước này thì category không hiện tên đúng
trong inserter (rơi về "Uncategorized").

Ngoại lệ — **block dùng chung, KHÔNG gắn riêng cho 1 site** (tiện ích thuần
tuý, không mang thiết kế riêng của site nào — vd `block-video`,
`block-shortcode-widget`): giữ nguyên category `lacadev-blocks`, KHÔNG gán
site.

Khi chưa xác định rõ 1 block thuộc site nào (block cũ, chưa rõ nguồn gốc):
tạm gán `site-unclassified` (category "Site khác (chưa phân loại)") thay vì
đoán bừa — sửa lại đúng site khi xác nhận được, đừng để mãi ở trạng thái
tạm.

## 3. Mọi PHP helper dùng chung phải COPY vào từng thư mục block, không được `require` chéo qua `utils/`

Cơ chế Block Marketplace sync (`BlockSyncSender::encodeDirectoryFiles()` /
`BlockCatalogProvider::encodeDirectoryFiles()`) chỉ đóng gói **đúng 1 thư mục
block** khi đẩy sang site khách — không kéo theo thư mục `utils/` hay bất kỳ
thư mục anh em nào khác. Nếu 1 block `require '../utils/xxx.php'`, site khách
nhận block đó sẽ bị fatal error (file không tồn tại).

→ `utils/*.php` chỉ là **bản tham chiếu** để copy, KHÔNG được require trực
tiếp từ block nào. Khi 1 block cần dùng chung logic PHP (icon helper, query
builder…), copy nguyên file đó vào thư mục của chính block, rồi
`require_once __DIR__ . '/ten-file.php';`. Ví dụ đã áp dụng:
`icons.php` (icon SVG) và `post-source-query.php` (build WP_Query) đều tồn
tại dưới dạng nhiều bản copy độc lập trong từng block cần dùng.

Ngược lại, `utils/*.js` (import qua webpack) an toàn khi dùng chung — webpack
bundle mọi import vào 1 file `build/index.js` duy nhất cho từng block, nên
`../utils/post-source-controls.js`, `../utils/preview.js` không có vấn đề
sync vì kết quả build ra vẫn tự chứa trong `build/` của block đó.

## 4. Block hiển thị NHIỀU bài viết/CPT phải có 2 chế độ nhập: Thủ công + Tự động

Bất kỳ block nào hiển thị 1 danh sách bài viết (dịch vụ, sản phẩm, tin tức,
đội ngũ…) đều nên hỗ trợ song song:

- **Thủ công (`manual`)**: chọn 1 CPT, tìm kiếm nhanh theo từ khóa, chọn tay
  nhiều bài viết cụ thể.
- **Tự động (`auto`)**: chọn 1 CPT, lọc theo taxonomy/term (tuỳ chọn), số bài
  hiển thị, sắp xếp theo ngày/tiêu đề/menu order/**ngẫu nhiên**.

Một số block (vd Feature Cards) còn giữ thêm chế độ thứ 3 `custom` (nhập tay
từng thẻ với đầy đủ trường ảnh/tiêu đề/mô tả/link riêng biệt) làm mặc định để
tương thích ngược — KHÔNG bắt buộc cho block mới, chỉ dùng khi block đã tồn
tại trước khi thêm 2 chế độ trên.

### Dùng lại `utils/post-source-controls.js` + `utils/post-source-query.php`

Đã có sẵn, generalize từ `block-posts-highlight` — không viết lại logic
fetch postType/taxonomy/terms/tìm bài viết mỗi lần tạo block mới.

**Contract thuộc tính bắt buộc** (khai báo đúng tên này trong `block.json`):

| Thuộc tính      | Type    | Default   | Ghi chú                                   |
|-----------------|---------|-----------|--------------------------------------------|
| `mode`          | string  | `"auto"`  | `'auto' \| 'manual'` (+ giá trị riêng nếu cần, vd `'custom'`) |
| `postType`      | string  | `"post"`  | slug CPT                                   |
| `taxonomy`      | string  | `""`      | slug taxonomy đang lọc, rỗng = không lọc   |
| `selectedTerms` | array   | `[]`      | term ID đã chọn (mode auto)                |
| `postsCount`    | number  | tuỳ block | số bài lấy ra (mode auto)                  |
| `orderBy`       | string  | `"date"`  | `date \| title \| menu_order \| rand`      |
| `order`         | string  | `"DESC"`  | `ASC \| DESC`, bỏ qua khi `orderBy==='rand'`|
| `selectedPosts` | array   | `[]`      | post ID đã chọn tay (mode manual)           |

JS (`edit.js`):
```js
import { PostSourceControls, ColumnsControl } from '../utils/post-source-controls';

<PostSourceControls
	attributes={ attributes }
	setAttributes={ setAttributes }
	extraModeOptions={ [ /* optional: thêm mode riêng của block, vd 'custom' */ ] }
/>
```

PHP (`render.php`) — copy `post-source-query.php` vào thư mục block (xem
mục 2), rồi:
```php
require_once __DIR__ . '/post-source-query.php';
$query_args = lcdc_build_post_source_query( $attributes, 'post', 6 );
$loop = new WP_Query( $query_args );
// ... map $loop->posts sang shape card của block, rồi wp_reset_postdata();
```

## 5. Block dạng lưới (grid/cards) phải có control chọn số cột

Dùng chung `ColumnsControl` trong `utils/post-source-controls.js` (RangeControl
2–6 cột mặc định). Lưu vào 1 attribute `columns` (number), truyền xuống PHP
qua CSS custom property, KHÔNG hard-code số cột trong SCSS:

```scss
&__grid {
	grid-template-columns: 1fr; // mobile
	@media (min-width: 768px) { grid-template-columns: repeat(2, 1fr); } // tablet cố định 2 cột
	@media (min-width: 1200px) { grid-template-columns: repeat(var(--fc-columns, 4), 1fr); } // desktop theo control
}
```
```php
$wrapper_style = sprintf( '...--fc-columns:%d;', $columns );
```

## 6. Preview khi hover trong inserter phải dùng ảnh `preview.png` thật

Mọi block đều có sẵn `useInserterPreview()` + `BlockPreviewMock` (từ
`utils/preview.js`). Khi tạo block mới, LUÔN chụp/tạo 1 `preview.png` trong
thư mục block và truyền vào:
```js
import previewImage from './preview.png';
...
if ( isPreview ) {
	return <BlockPreviewMock kicker="..." title="..." columns={ 3 } image={ previewImage } />;
}
```
Không truyền `image` → rơi về mock UI tối màu generic (chấp nhận được cho
block phụ, nhưng nên tránh cho block chính).

## 7. Icon: luôn dùng SVG inline tự-chứa kích thước, KHÔNG dùng icon font, KHÔNG tra tên qua bộ icon tự vẽ

Đã bỏ Material Symbols/Google Font (không đáng tin cậy, phụ thuộc CDN).

**Icon CỐ ĐỊNH, không cho admin đổi** (vd icon mũi tên CTA, icon điện thoại
trong 1 form liên hệ cố định…): vẫn dùng 1 helper PHP tự vẽ SVG nội bộ như
`lcdc_dental_icon()` (copy theo đúng mục 2, mỗi block 1 bản `icons.php`), vì
số lượng icon cần ít và cố định, không đáng để bắt admin tự cung cấp.

**Icon do ADMIN CHỌN qua danh sách mục** (item icon trong 1 mảng lặp — stats,
feature list…): **KHÔNG** dùng ô nhập "tên icon" tra qua 1 bộ SVG tự vẽ giới
hạn — bộ tự vẽ sẽ không bao giờ khớp hình dạng thật của icon trên
fonts.google.com/icons (chỉ là hình minh hoạ gần đúng), gây lệch icon so với
thiết kế. Thay vào đó dùng chung `utils/icon-input.js` (JS) +
`utils/icon-render.php` (PHP, copy theo mục 2) — cho admin 2 lựa chọn: dán
thẳng SVG code (lấy từ fonts.google.com/icons, tab **"SVG"**, không phải
phần font-embed/`<span class="material-symbols-outlined">`) hoặc tải ảnh lên.

Contract dữ liệu 1 icon: `{ type: 'svg'|'image', svg: string, imageId: number, imageUrl: string }`.

JS (`edit.js`):
```js
import { IconInput } from '../utils/icon-input';

<IconInput
	icon={ typeof item.icon === 'string' || ! item.icon ? { type: 'svg', svg: '' } : item.icon }
	onChange={ ( v ) => updateItem( index, 'icon', v ) }
/>
```
(Điều kiện `typeof item.icon === 'string'` chỉ cần khi RETROFIT 1 block đã có
sẵn dữ liệu cũ dạng tên icon — block mới tạo từ đầu không cần dòng này.)

PHP (`render.php`) — copy `icon-render.php` vào thư mục block, rồi:
```php
require_once __DIR__ . '/icon-render.php';
echo lcdc_render_icon( $item['icon'] ?? null );
```
`lcdc_render_icon()` tự ép icon (SVG dán tay hoặc ảnh) về đúng khung 1em,
không phụ thuộc `width`/`height` khai báo trong SVG gốc. CSS của block cần
thêm 1 rule để SVG lấp đầy khung:
```scss
&__icon {
	font-size: 2rem; // kích thước icon = font-size (icon dùng đơn vị em)
	.lcdc-icon svg { width: 100%; height: 100%; display: block; }
}
```

**Lưu ý khi retrofit 1 block đang dùng tên icon cũ**: đổi contract dữ liệu
(string → object) sẽ làm dữ liệu ĐÃ NHẬP của các instance đã đặt trên trang
thật mất hiệu lực (icon trống, cần admin chọn lại) — phải báo trước cho
người yêu cầu, không tự ý coi là "chi tiết ngầm".

## 8. Margin âm / hiệu ứng overlap giữa các section: phải vô hiệu hoá trong editor

Nếu 1 block dùng margin âm để đè lên block phía trên (hiệu ứng overlap chủ ý
ở frontend), margin đó sẽ làm hỏng layout trong danh sách block của editor —
phải tắt khi đang render cho ServerSideRender:
```php
$is_editor_context = defined( 'REST_REQUEST' ) && REST_REQUEST;
$margin_top_css    = $is_editor_context ? '0' : ( '-' . $pull_up_overlap . 'px' );
```

## 9. Trước khi bàn giao 1 block/nhóm block mới

- `php -l` toàn bộ `render.php`/`*.php` mới hoặc sửa.
- `node -e "JSON.parse(...)"` mọi `block.json` mới hoặc sửa.
- `npx wp-scripts build --config resources/build/webpack.blocks.js` — build
  toàn bộ `block-gutenberg/`, xác nhận `webpack ... compiled successfully`
  cho từng block, không chỉ block vừa sửa (build là 1 lệnh chung cho tất cả).
