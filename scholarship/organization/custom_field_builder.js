(function () {
    var container = document.getElementById("custom-fields-container");
    var addButton = document.getElementById("btn-add-custom-field");
    var form = container ? container.closest("form") : null;
    var nextId = container ? container.children.length : 0;
    var reservedLabels = [
        "gpa", "成績", "gpa成績", "學業成績",
        "排名", "班排", "系排", "班排名", "系排名", "班排系排",
        "推薦信", "推薦人", "推薦教師", "推薦人email", "推薦教師email"
    ];

    if (!container || !addButton || !form) {
        return;
    }

    function normalizeLabel(value) {
        return String(value || "")
            .trim()
            .toLocaleLowerCase()
            .replace(/[\s\u3000\-_\/]+/g, "");
    }

    function labelInputs() {
        return Array.prototype.slice.call(
            container.querySelectorAll('input[name="custom_labels[]"]')
        );
    }

    function duplicateInput() {
        var seen = Object.create(null);
        var inputs = labelInputs();
        for (var i = 0; i < inputs.length; i++) {
            var normalized = normalizeLabel(inputs[i].value);
            if (normalized && seen[normalized]) {
                return inputs[i];
            }
            if (normalized) {
                seen[normalized] = true;
            }
        }
        return null;
    }

    function updateReservedState(input) {
        var reserved = reservedLabels.indexOf(normalizeLabel(input.value)) !== -1;
        input.classList.toggle("is-invalid", reserved);
        input.setCustomValidity(reserved ? "這是系統固定欄位，不可重複新增。" : "");
        return reserved;
    }

    function reservedInput() {
        var inputs = labelInputs();
        for (var i = 0; i < inputs.length; i++) {
            if (updateReservedState(inputs[i])) {
                return inputs[i];
            }
        }
        return null;
    }

    function hasLabel(label) {
        var target = normalizeLabel(label);
        return labelInputs().some(function (input) {
            return normalizeLabel(input.value) === target;
        });
    }

    function updatePresetButtons() {
        document.querySelectorAll("[data-custom-preset]").forEach(function (button) {
            button.disabled = hasLabel(button.dataset.label);
        });
    }

    function addRow(values) {
        values = values || {};
        nextId++;

        var row = document.createElement("div");
        row.className = "custom-field-row bg-white p-3 rounded border";
        row.id = "custom-field-row-" + nextId;
        row.innerHTML = `
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small text-secondary fw-semibold">項目名稱</label>
                    <input type="text" name="custom_labels[]" class="form-control" placeholder="請輸入項目名稱" required>
                    <div class="invalid-feedback">此為系統固定欄位，不可重複新增。</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-secondary fw-semibold">欄位型態</label>
                    <select name="custom_types[]" class="form-select">
                        <option value="text">單行文字輸入框</option>
                        <option value="number">數字輸入框</option>
                        <option value="textarea">多行文字區塊</option>
                        <option value="file">檔案上傳</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-secondary fw-semibold">是否必填</label>
                    <select name="custom_required[]" class="form-select">
                        <option value="1">必填</option>
                        <option value="0">選填</option>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm" data-remove-custom-field>移除</button>
                </div>
                <div class="col-12">
                    <label class="form-label small text-secondary fw-semibold">備註（選填，顯示給學生）</label>
                    <input type="text" name="custom_notes[]" class="form-control" maxlength="500"
                           placeholder="例如：請上傳最近一學期、需包含學校核章">
                </div>
            </div>
        `;

        row.querySelector('[name="custom_labels[]"]').value = values.label || "";
        row.querySelector('[name="custom_types[]"]').value = values.type || "text";
        row.querySelector('[name="custom_required[]"]').value = String(values.required === undefined ? 1 : values.required);
        row.querySelector('[name="custom_notes[]"]').value = values.note || "";
        container.appendChild(row);
        updatePresetButtons();
        row.querySelector('[name="custom_labels[]"]').focus();
    }

    addButton.addEventListener("click", function () {
        addRow();
    });

    document.querySelectorAll("[data-custom-preset]").forEach(function (button) {
        button.addEventListener("click", function () {
            if (hasLabel(button.dataset.label)) {
                alert("這個欄位已經加入，不能重複新增。");
                return;
            }
            addRow({
                label: button.dataset.label,
                type: button.dataset.type || "file",
                required: button.dataset.required || "0",
                note: button.dataset.note || ""
            });
        });
    });

    container.addEventListener("click", function (event) {
        var removeButton = event.target.closest("[data-remove-custom-field]");
        if (!removeButton) {
            return;
        }
        removeButton.closest(".custom-field-row").remove();
        updatePresetButtons();
    });

    container.addEventListener("input", function (event) {
        if (event.target.matches('input[name="custom_labels[]"]')) {
            updateReservedState(event.target);
        }
        updatePresetButtons();
    });

    form.addEventListener("submit", function (event) {
        var reserved = reservedInput();
        if (reserved) {
            event.preventDefault();
            alert("GPA、成績、排名與推薦信等系統固定欄位，不可重複新增。");
            reserved.focus();
            return;
        }
        var duplicate = duplicateInput();
        if (!duplicate) {
            return;
        }
        event.preventDefault();
        alert("自訂欄位名稱不可重複，請修改後再送出。");
        duplicate.focus();
    });

    labelInputs().forEach(updateReservedState);
    updatePresetButtons();
})();
