/**
 * Visual Page Builder (Phase 11) - vanilla JS thuan, khong thu vien ngoai. Quan ly 1 mang blocks
 * trong bo nho, render lai toan bo danh sach moi lan thay doi (khong virtual DOM - du don gian voi
 * so luong block thuc te cua 1 Page), keo-tha sap xep bang HTML5 draggable thuan (cung ky thuat da
 * dung o Menu Builder - Phase 3.3), serialize ra hidden input #content-blocks-input truoc khi submit.
 */
(function () {
    'use strict';

    var root = document.getElementById('block-builder');

    if (!root) {
        return;
    }

    var listEl = document.getElementById('block-list');
    var hiddenInput = document.getElementById('content-blocks-input');
    var form = document.getElementById('page-form');

    var images = [];
    var blocks = [];

    try {
        images = JSON.parse(root.getAttribute('data-images') || '[]');
    } catch (e) {
        images = [];
    }

    try {
        blocks = JSON.parse(root.getAttribute('data-initial-blocks') || '[]');
    } catch (e) {
        blocks = [];
    }

    var draggedIndex = null;

    function defaultBlockFor(type) {
        switch (type) {
            case 'heading':
                return { type: 'heading', text: '', level: 2 };
            case 'paragraph':
                return { type: 'paragraph', text: '' };
            case 'image':
                return { type: 'image', media_id: '', alt: '' };
            case 'hero':
                return { type: 'hero', headline: '', subheadline: '', cta_label: '', cta_url: '' };
            case 'feature_grid':
                return { type: 'feature_grid', items: [] };
            case 'cta':
                return { type: 'cta', headline: '', button_label: '', button_url: '' };
            default:
                return null;
        }
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);

        return div.innerHTML;
    }

    function imageOptions(selectedId) {
        var html = '<option value="">-- Chọn ảnh --</option>';

        for (var i = 0; i < images.length; i++) {
            var selected = String(images[i].id) === String(selectedId) ? ' selected' : '';
            html += '<option value="' + escapeHtml(images[i].id) + '"' + selected + '>' + escapeHtml(images[i].file_name) + '</option>';
        }

        return html;
    }

    function fieldsHtmlFor(block, index) {
        var idPrefix = 'block-' + index + '-';

        if (block.type === 'heading') {
            return ''
                + '<div class="field mb-0"><label for="' + idPrefix + 'text">Nội dung tiêu đề</label>'
                + '<input type="text" id="' + idPrefix + 'text" data-field="text" value="' + escapeHtml(block.text) + '"></div>'
                + '<div class="field mb-0"><label for="' + idPrefix + 'level">Cấp độ</label>'
                + '<select id="' + idPrefix + 'level" data-field="level">'
                + [2, 3, 4].map(function (lvl) {
                    return '<option value="' + lvl + '"' + (Number(block.level) === lvl ? ' selected' : '') + '>H' + lvl + '</option>';
                }).join('')
                + '</select></div>';
        }

        if (block.type === 'paragraph') {
            return '<div class="field mb-0"><label for="' + idPrefix + 'text">Nội dung đoạn văn</label>'
                + '<textarea id="' + idPrefix + 'text" data-field="text" rows="3">' + escapeHtml(block.text) + '</textarea></div>';
        }

        if (block.type === 'image') {
            return ''
                + '<div class="field mb-0"><label for="' + idPrefix + 'media">Chọn ảnh</label>'
                + '<select id="' + idPrefix + 'media" data-field="media_id">' + imageOptions(block.media_id) + '</select></div>'
                + '<div class="field mb-0"><label for="' + idPrefix + 'alt">Văn bản thay thế (Alt)</label>'
                + '<input type="text" id="' + idPrefix + 'alt" data-field="alt" value="' + escapeHtml(block.alt) + '"></div>';
        }

        if (block.type === 'hero') {
            return ''
                + '<div class="field mb-0"><label for="' + idPrefix + 'headline">Tiêu đề chính</label><input type="text" id="' + idPrefix + 'headline" data-field="headline" value="' + escapeHtml(block.headline) + '"></div>'
                + '<div class="field mb-0"><label for="' + idPrefix + 'subheadline">Tiêu đề phụ</label><input type="text" id="' + idPrefix + 'subheadline" data-field="subheadline" value="' + escapeHtml(block.subheadline) + '"></div>'
                + '<div class="field mb-0"><label for="' + idPrefix + 'cta_label">Nhãn nút CTA</label><input type="text" id="' + idPrefix + 'cta_label" data-field="cta_label" value="' + escapeHtml(block.cta_label) + '"></div>'
                + '<div class="field mb-0"><label for="' + idPrefix + 'cta_url">URL nút CTA</label><input type="text" id="' + idPrefix + 'cta_url" data-field="cta_url" value="' + escapeHtml(block.cta_url) + '"></div>';
        }

        if (block.type === 'cta') {
            return ''
                + '<div class="field mb-0"><label for="' + idPrefix + 'headline">Tiêu đề chính</label><input type="text" id="' + idPrefix + 'headline" data-field="headline" value="' + escapeHtml(block.headline) + '"></div>'
                + '<div class="field mb-0"><label for="' + idPrefix + 'button_label">Nhãn nút</label><input type="text" id="' + idPrefix + 'button_label" data-field="button_label" value="' + escapeHtml(block.button_label) + '"></div>'
                + '<div class="field mb-0"><label for="' + idPrefix + 'button_url">URL nút</label><input type="text" id="' + idPrefix + 'button_url" data-field="button_url" value="' + escapeHtml(block.button_url) + '"></div>';
        }

        if (block.type === 'feature_grid') {
            var itemsHtml = (block.items || []).map(function (item, itemIndex) {
                return ''
                    + '<div class="block-editor-item" style="margin-bottom:8px;" data-feature-index="' + itemIndex + '">'
                    + '<input type="text" data-feature-field="icon" placeholder="Icon" value="' + escapeHtml(item.icon) + '" style="width:60px; display:inline-block;">'
                    + '<input type="text" data-feature-field="title" placeholder="Tiêu đề" value="' + escapeHtml(item.title) + '" style="width:200px; display:inline-block;">'
                    + '<input type="text" data-feature-field="description" placeholder="Mô tả" value="' + escapeHtml(item.description) + '" style="width:260px; display:inline-block;">'
                    + '<button type="button" class="btn btn-danger btn-sm" data-remove-feature="' + itemIndex + '">Xoá</button>'
                    + '</div>';
            }).join('');

            return '<div data-feature-list>' + itemsHtml + '</div>'
                + '<button type="button" class="btn btn-secondary btn-sm" data-add-feature>+ Thêm tính năng</button>';
        }

        return '';
    }

    function labelFor(type) {
        var labels = {
            heading: 'Tiêu đề (Heading)',
            paragraph: 'Đoạn văn (Paragraph)',
            image: 'Hình ảnh (Image)',
            hero: 'Khối Hero',
            feature_grid: 'Lưới tính năng',
            cta: 'Khối CTA',
        };

        return labels[type] || type;
    }

    function render() {
        listEl.innerHTML = blocks.map(function (block, index) {
            return ''
                + '<div class="block-editor-item" draggable="true" data-block-index="' + index + '">'
                + '<div class="block-editor-toolbar">'
                + '<span class="drag-handle" aria-hidden="true">::</span>'
                + '<strong>' + escapeHtml(labelFor(block.type)) + '</strong>'
                + '<button type="button" class="btn btn-danger btn-sm" data-remove-block="' + index + '">Xoá Block</button>'
                + '</div>'
                + '<div class="block-editor-fields">' + fieldsHtmlFor(block, index) + '</div>'
                + '</div>';
        }).join('');

        if (blocks.length === 0) {
            listEl.innerHTML = '<div class="empty-state">Chưa có block nào. Bấm nút bên dưới để thêm.</div>';
        }

        serialize();
    }

    function serialize() {
        hiddenInput.value = JSON.stringify(blocks);
    }

    listEl.addEventListener('input', function (event) {
        var itemEl = event.target.closest('[data-block-index]');

        if (!itemEl) {
            return;
        }

        var index = parseInt(itemEl.getAttribute('data-block-index'), 10);
        var block = blocks[index];

        if (!block) {
            return;
        }

        var featureItemEl = event.target.closest('[data-feature-index]');

        if (featureItemEl && event.target.hasAttribute('data-feature-field')) {
            var featureIndex = parseInt(featureItemEl.getAttribute('data-feature-index'), 10);
            var featureField = event.target.getAttribute('data-feature-field');
            block.items[featureIndex][featureField] = event.target.value;
            serialize();

            return;
        }

        if (event.target.hasAttribute('data-field')) {
            var field = event.target.getAttribute('data-field');
            block[field] = field === 'level' ? parseInt(event.target.value, 10) : event.target.value;
            serialize();
        }
    });

    listEl.addEventListener('click', function (event) {
        var removeBlockBtn = event.target.closest('[data-remove-block]');

        if (removeBlockBtn) {
            var removeIndex = parseInt(removeBlockBtn.getAttribute('data-remove-block'), 10);
            blocks.splice(removeIndex, 1);
            render();

            return;
        }

        var addFeatureBtn = event.target.closest('[data-add-feature]');

        if (addFeatureBtn) {
            var featureBlockEl = addFeatureBtn.closest('[data-block-index]');
            var featureBlockIndex = parseInt(featureBlockEl.getAttribute('data-block-index'), 10);

            if (!blocks[featureBlockIndex].items) {
                blocks[featureBlockIndex].items = [];
            }

            blocks[featureBlockIndex].items.push({ icon: '', title: '', description: '' });
            render();

            return;
        }

        var removeFeatureBtn = event.target.closest('[data-remove-feature]');

        if (removeFeatureBtn) {
            var removeFeatureBlockEl = removeFeatureBtn.closest('[data-block-index]');
            var removeFeatureBlockIndex = parseInt(removeFeatureBlockEl.getAttribute('data-block-index'), 10);
            var removeFeatureIndex = parseInt(removeFeatureBtn.getAttribute('data-remove-feature'), 10);
            blocks[removeFeatureBlockIndex].items.splice(removeFeatureIndex, 1);
            render();
        }
    });

    // ---- Drag-drop sap xep thu tu (HTML5 native, cung ky thuat Menu Builder) ----

    listEl.addEventListener('dragstart', function (event) {
        var itemEl = event.target.closest('[data-block-index]');

        if (!itemEl) {
            return;
        }

        draggedIndex = parseInt(itemEl.getAttribute('data-block-index'), 10);
        itemEl.classList.add('is-dragging');
    });

    listEl.addEventListener('dragend', function () {
        document.querySelectorAll('.block-editor-item.is-dragging').forEach(function (el) {
            el.classList.remove('is-dragging');
        });
        draggedIndex = null;
    });

    listEl.addEventListener('dragover', function (event) {
        event.preventDefault();
    });

    listEl.addEventListener('drop', function (event) {
        event.preventDefault();
        var targetEl = event.target.closest('[data-block-index]');

        if (!targetEl || draggedIndex === null) {
            return;
        }

        var targetIndex = parseInt(targetEl.getAttribute('data-block-index'), 10);

        if (targetIndex === draggedIndex) {
            return;
        }

        var moved = blocks.splice(draggedIndex, 1)[0];
        blocks.splice(targetIndex, 0, moved);
        render();
    });

    // ---- Them block moi ----

    document.querySelectorAll('[data-add-block]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var type = btn.getAttribute('data-add-block');
            var newBlock = defaultBlockFor(type);

            if (newBlock) {
                blocks.push(newBlock);
                render();
            }
        });
    });

    // ---- Toggle giua Rich Text (Quill) va Block Builder ----

    var modeInput = document.getElementById('editor-mode-input');
    var quillPane = document.getElementById('quill-pane');
    var blockPane = document.getElementById('block-builder-pane');

    document.querySelectorAll('[data-editor-mode-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var mode = btn.getAttribute('data-editor-mode-toggle');
            modeInput.value = mode;
            quillPane.style.display = mode === 'quill' ? '' : 'none';
            blockPane.style.display = mode === 'block' ? '' : 'none';
        });
    });

    if (form) {
        form.addEventListener('submit', function () {
            serialize();
        });
    }

    render();
})();
