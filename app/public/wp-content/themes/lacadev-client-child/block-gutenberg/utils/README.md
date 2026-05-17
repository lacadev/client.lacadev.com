# Block Gutenberg Utils

Thư mục này giữ các helper dùng chung cho block của child theme.

Lưu ý cấu trúc:

- `utils/`: JS helpers + PHP render helpers
- `common/`: SCSS dùng chung giữa nhiều block

## Mặc định chung cho block mới

File [block-defaults.js](./block-defaults.js) cung cấp bộ scaffold mặc định để block mới có ngay:

- `heading`
- `subheading`
- `headingTag`
- `headingAlign`
- `subheadingAlign`
- `headingColor`
- `subheadingColor`
- `bgColor`
- `bgOpacity`
- `spacing`
- fallback spacing cũ: `marginTop`, `marginBottom`, `paddingTop`, `paddingBottom`
- `__isPreview` nếu block cần mock preview trong inserter

## Cách dùng khuyến nghị

### 1. Trong `index.js`

```js
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';

registerBlockType( metadata.name, {
  edit: Edit,
  save: Save,
} );
```

`block.json` là nguồn truth duy nhất cho common attributes.
Không nên override lại `attributes` trong `index.js`, vì rất dễ lệch giữa editor metadata và runtime metadata.

### 2. Trong `edit.js`

```js
import {
  BlockBasePanels,
  normalizeBlockScaffoldAttributes,
  SectionHeaderToolbar,
  BlockSectionHeaderPreview,
} from '../utils';

const normalizedAttributes = normalizeBlockScaffoldAttributes(attributes, {
  bgColor: '#0f0f0f',
});
```

Sau đó dùng `BlockBasePanels` để block mới có sẵn inspector theo 3 lớp rõ ràng:

- `configPanelTitle`: phần cấu hình chức năng riêng của block
- `commonContentPanelTitle`: phần nội dung chung như `heading`, `subheading`
- `commonStylePanelTitle`: phần style chung như `headingTag`, căn lề, màu, background, spacing responsive

Ví dụ:

```js
<BlockBasePanels
  attributes={ normalizedAttributes }
  setAttributes={ setAttributes }
  textdomain="lacadev"
  configPanelTitle="Cấu hình video"
  commonContentPanelTitle="Nội dung chung"
  commonStylePanelTitle="Style chung"
  titleLabel="Tiêu đề video"
  subtitleLabel="Mô tả video"
  configChildren={ <YourBlockSpecificFields /> }
/>
```

Như vậy khi thêm block mới:

- field chức năng chính của block nằm tách biệt
- field dùng chung của block luôn ở cùng một chỗ
- editor UI giữa các block giữ cấu trúc nhất quán

### 3. Trong `block.json`

Nếu block là dynamic block hoặc cần server-side render, vẫn nên giữ khai báo attribute tương ứng trong `block.json` để metadata phía PHP/WordPress đồng bộ với editor.

### 4. Trong `render.php`

Nếu là dynamic block, ưu tiên dùng helper PHP chung:

- `render-helpers.php` cho spacing vars dùng chung
- `render-helpers.php` cho render section header dùng chung

Như vậy block mới sẽ không phải copy lại logic `spacing` và `heading/subheading` ở từng `render.php`.

### 5. Trong `style.scss`

Nếu block cần dùng style chung, import từ `block-gutenberg/common/`:

```scss
@use '../common/section-header';
```

## Mục tiêu thiết kế

- Block mới có khung cấu hình chung ngay từ đầu.
- Field dùng chung không phải copy/paste lại từng block.
- Inspector của các block giữ cùng trải nghiệm chỉnh sửa.

## Bộ block core hiện có

- `block-section-header`
  Header section dùng chung, chỉ lo title/subtitle + divider + max width.
- `block-cta`
  CTA với tiêu đề, mô tả và 2 nút.
- `block-hero`
  Hero banner với media, badge, mô tả và CTA.
- `block-feature-grid`
  Danh sách tính năng/dịch vụ dạng grid.
- `block-accordion`
  FAQ / accordion.
- `block-testimonial`
  Review / testimonial.
- `block-post-list`
  Danh sách bài viết động theo post type.
- `block-video`
  Video block mẫu, đã được chuẩn hoá theo scaffold chung.

## Helper mới

- `items.js`
  Helper chung để thêm / sửa / xoá item array cho các block có repeater đơn giản.
- `render-helpers.php`
  Bổ sung helper chung cho:
  - bool attribute
  - background rgba
  - wrapper spacing vars
  - render button

## Generator nội bộ

- `block-gutenberg/tools/generate-core-blocks.php`
  Script nội bộ để tái tạo bộ block core theo cùng scaffold chuẩn, đồng thời generate `preview.png` cho từng block.
