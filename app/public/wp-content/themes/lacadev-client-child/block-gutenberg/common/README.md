# Block Gutenberg Common Styles

Thư mục này giữ các SCSS dùng chung cho nhiều block trong child theme.

Hiện có:

- `_section-header.scss`
  Style dùng chung cho phần tiêu đề/sub tiêu đề của block.

Nguyên tắc:

- `utils/` chỉ giữ JS/PHP helpers.
- `common/` giữ style dùng chung giữa nhiều block.
- Mỗi block import các partial cần dùng từ `../common/...`.
