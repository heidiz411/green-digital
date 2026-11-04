/* สูตรจำ: อ่าน → เปิด → เติม → ส่ง → ตรวจ → โหลดใหม่ */
$(async function () {
  const api = "?r=/api/";
  const csrf = $("meta[name=csrf-token]").attr("content");
  const esc = (value) =>
    String(value ?? "").replace(
      /[&<>"']/g,
      (ch) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[ch],
    );
  const modal = bootstrap.Modal.getOrCreateInstance(
    document.getElementById("editor"),
  );
  const page = new URLSearchParams(location.search).get("page") || "dashboard";
  let user,
    table,
    options,
    activeKind,
    charts = [],
    fieldSequence = 0;
  const titles = {
    records: "รายการส่งขยะ",
    types: "ประเภทขยะ",
    organizations: "องค์กร",
    users: "สมาชิก",
    contents: "ความรู้รักษ์โลก",
    reports: "แจ้งปัญหาและคำตอบ",
  };
  const isStaff = () => ["admin", "staff"].includes(user?.role);
  function notice(message, error = false) {
    $("#notice")
      .removeClass("d-none alert-success alert-danger")
      .addClass(error ? "alert-danger" : "alert-success")
      .text(message)
      .trigger("focus");
  }
  function error(xhr) {
    const message =
      xhr.responseJSON?.message || "เชื่อมต่อไม่สำเร็จ กรุณาลองใหม่";
    if ($("#editor").hasClass("show"))
      $("#modalNotice").removeClass("d-none").text(message);
    else notice(message, true);
  }
  function get(action, data = {}) {
    return $.ajax({ url: api + action, data, dataType: "json" });
  }
  function post(action, data) {
    if (data instanceof FormData) {
      data.set("_csrf", csrf);
      return $.ajax({
        url: api + action,
        method: "POST",
        data,
        dataType: "json",
        processData: false,
        contentType: false,
      });
    }
    return $.ajax({
      url: api + action,
      method: "POST",
      data: { ...data, _csrf: csrf },
      dataType: "json",
    });
  }
  function field(name, label, type = "text", value = "", extra = "") {
    const id = "f_" + name + "_" + ++fieldSequence;
    return `<label class="form-label" for="${id}">${esc(label)}</label><input class="form-control" id="${id}" name="${name}" type="${type}" value="${esc(value)}" ${extra}>`;
  }
  function area(name, label, value = "") {
    const id = "f_" + name + "_" + ++fieldSequence;
    return `<label class="form-label" for="${id}">${esc(label)}</label><textarea class="form-control" id="${id}" name="${name}">${esc(value)}</textarea>`;
  }
  function select(name, label, items, value) {
    const id = "f_" + name + "_" + ++fieldSequence;
    return `<label class="form-label" for="${id}">${label}</label><select class="form-select" id="${id}" name="${name}">${items.map((x) => `<option value="${esc(x[0])}" ${String(x[0]) === String(value) ? "selected" : ""}>${esc(x[1])}</option>`).join("")}</select>`;
  }
  function formAuth(title, action, fields, button = "บันทึก") {
    $("#app").html(
      `<div class="card auth p-4"><h2 class="section-title">${title}</h2><form id="authForm" data-action="${action}">${fields}<button class="btn btn-success w-100 mt-4">${button}</button></form><p class="mt-3"><a href="?page=login">เข้าสู่ระบบ</a> · <a href="?page=register">สมัครสมาชิก</a> · <a href="?page=forgot">ลืมรหัสผ่าน</a></p></div>`,
    );
  }
  try {
    user = (await get("session")).user;
  } catch (xhr) {
    error(xhr);
    return;
  }
  if (!user) {
    if (page === "register")
      formAuth(
        "สมัครสมาชิก",
        "register",
        field(
          "full_name",
          "ชื่อ–นามสกุล",
          "text",
          "",
          'required maxlength="120"',
        ) +
          field("email", "อีเมล", "email", "", "required") +
          field(
            "password",
            "รหัสผ่านอย่างน้อย 8 ตัว",
            "password",
            "",
            'required minlength="8"',
          ) +
          field(
            "password_confirm",
            "ยืนยันรหัสผ่าน",
            "password",
            "",
            "required",
          ),
        "สมัครสมาชิก",
      );
    else if (page === "forgot")
      formAuth(
        "กู้คืนบัญชี",
        "recover",
        field("email", "อีเมล", "email", "", "required") +
          field(
            "recovery_code",
            "รหัสกู้คืนที่ได้รับตอนสมัคร",
            "text",
            "",
            "required",
          ),
        "ยืนยันรหัสกู้คืน",
      );
    else
      formAuth(
        "ยินดีต้อนรับกลับ",
        "login",
        field(
          "email",
          "อีเมล",
          "email",
          "",
          'required autocomplete="username"',
        ) +
          field(
            "password",
            "รหัสผ่าน",
            "password",
            "",
            'required autocomplete="current-password"',
          ),
        "เข้าสู่ระบบ",
      );
  } else if (page === "profile") {
    $("#app").html(
      `<div class="row g-4"><div class="col-md-6"><div class="card p-4"><h2 class="section-title">โปรไฟล์</h2><form class="simple" data-action="profile">${field("full_name", "ชื่อ–นามสกุล", "text", user.full_name, "required")}${field("phone", "โทรศัพท์", "text", user.phone)}${area("address", "ที่อยู่", user.address)}<button class="btn btn-success mt-3">บันทึก</button></form></div></div><div class="col-md-6"><div class="card p-4"><h2 class="section-title">เปลี่ยนรหัสผ่าน</h2><form class="simple" data-action="password">${field("old_password", "รหัสเดิม", "password", "", "required")}${field("new_password", "รหัสใหม่อย่างน้อย 8 ตัว", "password", "", 'required minlength="8"')}<button class="btn btn-success mt-3">เปลี่ยนรหัสผ่าน</button></form><hr><h2 class="section-title">สร้างรหัสกู้คืนใหม่</h2><form class="simple" data-action="recovery">${field("old_password", "ยืนยันรหัสผ่านปัจจุบัน", "password", "", "required")}<button class="btn btn-outline-success mt-3">สร้างรหัสกู้คืน</button></form></div></div></div>`,
    );
  } else {
    try {
      options = await get("options");
    } catch (xhr) {
      error(xhr);
      return;
    }
    if (page === "dashboard") await dashboard();
    else if (
      [
        "records",
        "types",
        "organizations",
        "users",
        "contents",
        "reports",
      ].includes(page)
    )
      await listing(page);
    else notice("ไม่พบหน้านี้", true);
  }
  $(document).on("submit", "#authForm, .simple", async function (e) {
    e.preventDefault();
    const form = $(this),
      button = form.find("button");
    button.prop("disabled", true);
    const action = form.data("action");
    try {
      const r = await post(action, new FormData(this));
      if (!r.success) throw { responseJSON: r };
      if (action === "login") location.href = "?";
      else if (action === "recover")
        formAuth(
          "ตั้งรหัสผ่านใหม่",
          "reset",
          field(
            "new_password",
            "รหัสผ่านใหม่",
            "password",
            "",
            'required minlength="8"',
          ),
        );
      else {
        notice(
          r.message +
            (r.recovery_code
              ? "\nรหัสกู้คืน: " +
                r.recovery_code +
                "\nโปรดจดเก็บไว้ ก่อนออกจากหน้านี้"
              : ""),
        );
        if (["register", "reset"].includes(action)) form.remove();
        else if (["password", "recovery"].includes(action)) form[0].reset();
      }
    } catch (xhr) {
      error(xhr);
    } finally {
      button.prop("disabled", false);
    }
  });
  $("#logout").on("click", async () => {
    try {
      await post("logout", {});
      location.href = "?page=login";
    } catch (xhr) {
      error(xhr);
    }
  });
  function filters() {
    return `<form id="filters" class="row g-2 mb-3"><div class="col-md-2">${field("from", "จากวันที่", "date")}</div><div class="col-md-2">${field("to", "ถึงวันที่", "date")}</div><div class="col-md-2">${select("org_id", "องค์กร", [["", "ทุกองค์กร"], ...options.organizations.map((x) => [x.id, x.name])])}</div><div class="col-md-2">${select("type_id", "ประเภท", [["", "ทุกประเภท"], ...options.types.map((x) => [x.id, x.name])])}</div><div class="col-md-2">${field("q", "ค้นหาชื่อ / หมายเหตุ")}</div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-success">กรอง</button></div></form>`;
  }
  function params() {
    return Object.fromEntries(new FormData(document.getElementById("filters")));
  }
  async function listing(kind) {
    activeKind = kind;
    if (kind === "users" && user.role !== "admin") {
      notice("เฉพาะผู้ดูแลระบบ", true);
      return;
    }
    if (["types", "organizations"].includes(kind) && !isStaff()) {
      notice("เฉพาะเจ้าหน้าที่", true);
      return;
    }
    const canAdd =
      !["types", "organizations", "contents", "users"].includes(kind) ||
      isStaff();
    $("#app").html(
      `<div class="card p-4"><div class="d-flex flex-wrap justify-content-between align-items-center mb-3"><h2 class="section-title">${titles[kind]}</h2><div>${canAdd ? '<button class="btn btn-success" id="add">＋ เพิ่มข้อมูล</button>' : ""}<a class="btn btn-outline-success" id="export">ส่งออก CSV</a></div></div>${kind === "records" ? filters() : ""}<div class="table-responsive"><table id="grid" class="table table-striped w-100"></table></div></div>`,
    );
    const columns = {
      records: [
        ["id", "รหัส"],
        ["record_date", "วันที่"],
        ["full_name", "สมาชิก"],
        ["organization", "องค์กร"],
        ["type_name", "ประเภท"],
        ["weight", "กก."],
        ["status", "สถานะ"],
        ["review_note", "ผลตรวจ"],
      ],
      types: [
        ["id", "รหัส"],
        ["name", "ประเภท (กก.)"],
        ["price", "บาท/กก."],
      ],
      organizations: [
        ["id", "รหัส"],
        ["name", "องค์กร"],
      ],
      users: [
        ["id", "รหัส"],
        ["full_name", "ชื่อ"],
        ["email", "อีเมล"],
        ["role", "บทบาท"],
        ["active", "ใช้งาน 1/ระงับ 0"],
      ],
      contents: [
        ["id", "รหัส"],
        ["title", "หัวข้อ"],
        ["publish_at", "เผยแพร่"],
        ["expire_at", "หมดอายุ"],
      ],
      reports: [
        ["id", "รหัส"],
        ["full_name", "ผู้แจ้ง"],
        ["message", "ข้อความ"],
        ["reply", "คำตอบ"],
        ["is_read", "อ่านแล้ว 1/ยังไม่อ่าน 0"],
      ],
    }[kind];
    table = $("#grid").DataTable({
      ajax: {
        url: api + "list&kind=" + kind,
        dataSrc: "data",
        data: (d) => {
          if (kind === "records") Object.assign(d, params());
        },
        error,
      },
      columns: [
        ...columns.map(([data, title]) => ({
          data,
          title,
          defaultContent: "",
          render: function (value, type) {
            if (type !== "display") return value;
            const labels = {
              status: {
                pending: "รอตรวจ",
                approved: "ตรวจรับแล้ว",
                rejected: "ไม่ผ่าน",
              },
              role: {
                admin: "ผู้ดูแล",
                staff: "เจ้าหน้าที่",
                member: "สมาชิก",
              },
              active: { 1: "ใช้งาน", 0: "ระงับ" },
              is_read: { 1: "อ่านแล้ว", 0: "ยังไม่อ่าน" },
            };
            return esc(labels[data]?.[value] ?? value);
          },
        })),
        {
          data: null,
          title: "จัดการ",
          orderable: false,
          render: () =>
            '<button class="btn btn-sm btn-outline-success view">เปิด</button>',
        },
      ],
      order: [[0, "desc"]],
      language: {
        search: "ค้นหาในตาราง:",
        lengthMenu: "แสดง _MENU_ รายการ",
        info: "_START_–_END_ จาก _TOTAL_ รายการ",
        infoEmpty: "ไม่มีข้อมูล",
        zeroRecords: "ไม่พบข้อมูล",
        emptyTable: "ยังไม่มีรายการ",
        paginate: { next: "ถัดไป", previous: "ก่อนหน้า" },
      },
    });
    function exportUrl() {
      $("#export").attr("href", "#");
    }
    exportUrl();
    $("#export").on("click", function (e) {
      e.preventDefault();
      const rows = table.rows({ search: "applied" }).data().toArray();
      const csvCell = (v) =>
        '"' +
        String(v ?? "")
          .replace(/^[=+@\-\t\r]/, (m) => "'" + m)
          .replace(/"/g, '""') +
        '"';
      const csv = [
        columns.map((x) => csvCell(x[1])).join(","),
        ...rows.map((r) => columns.map(([k]) => csvCell(r[k])).join(",")),
      ].join("\r\n");
      const url = URL.createObjectURL(
        new Blob(["\uFEFF" + csv], { type: "text/csv;charset=utf-8" }),
      );
      const a = document.createElement("a");
      a.href = url;
      a.download = kind + ".csv";
      a.click();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
    $("#filters").on("submit", (e) => {
      e.preventDefault();
      table.ajax.reload();
      exportUrl();
    });
    $("#add").on("click", () => openEditor(kind, {}));
    $("#grid").on("click", ".view", async function () {
      const row = table.row($(this).closest("tr")).data();
      try {
        openEditor(kind, await get("detail", { kind, id: row.id }));
      } catch (xhr) {
        error(xhr);
      }
    });
  }
  function openEditor(kind, r) {
    $("#modalNotice").addClass("d-none").text("");
    if (kind === "contents" && !isStaff()) {
      $("#editorTitle").text(r.title);
      $("#fields").html(
        `<article><div class="content-body">${esc(r.body)}</div>${r.image ? `<img class="img-fluid rounded mt-3" alt="ภาพประกอบบทความ" src="${api}image&kind=contents&id=${r.id}">` : ""}</article>`,
      );
      $("#editForm button[type=submit]").hide();
      modal.show();
      return;
    }

    let html = `<input type="hidden" name="kind" value="${kind}"><input type="hidden" name="id" value="${r.id || 0}">`;
    if (kind === "types")
      html +=
        field(
          "name",
          "ชื่อประเภท",
          "text",
          r.name,
          'required maxlength="120"',
        ) +
        field(
          "price",
          "ราคาประเมิน บาท/กก.",
          "number",
          r.price ?? 0,
          'required min="0" step="0.01"',
        );
    if (kind === "organizations")
      html += field(
        "name",
        "ชื่อองค์กร",
        "text",
        r.name,
        'required maxlength="120"',
      );
    if (kind === "users")
      html +=
        field("full_name", "ชื่อ–นามสกุล", "text", r.full_name, "required") +
        field("email", "อีเมล", "email", r.email, "required") +
        select(
          "role",
          "บทบาท",
          [
            ["member", "สมาชิก"],
            ["staff", "เจ้าหน้าที่"],
            ["admin", "ผู้ดูแล"],
          ],
          r.role || "member",
        ) +
        select(
          "active",
          "สถานะ",
          [
            [1, "ใช้งาน"],
            [0, "ระงับ"],
          ],
          r.active ?? 1,
        ) +
        (!r.id
          ? field(
              "password",
              "รหัสผ่านเริ่มต้น",
              "password",
              "",
              'required minlength="8"',
            )
          : "");
    if (kind === "contents")
      html +=
        field("title", "หัวข้อ", "text", r.title, "required") +
        area("body", "รายละเอียด", r.body) +
        field(
          "publish_at",
          "เปิดแสดง (ว่าง = ทันที)",
          "datetime-local",
          r.publish_at?.replace(" ", "T").slice(0, 16),
        ) +
        field(
          "expire_at",
          "ปิดแสดง (ว่าง = ไม่หมดอายุ)",
          "datetime-local",
          r.expire_at?.replace(" ", "T").slice(0, 16),
        ) +
        select(
          "active",
          "สถานะ",
          [
            [1, "เผยแพร่"],
            [0, "ซ่อน"],
          ],
          r.active ?? 1,
        );
    if (kind === "records")
      html +=
        select(
          "org_id",
          "องค์กร",
          options.organizations.map((x) => [x.id, x.name]),
          r.org_id,
        ) +
        select(
          "type_id",
          "ประเภทขยะ",
          options.types.map((x) => [x.id, x.name]),
          r.type_id,
        ) +
        field(
          "weight",
          "น้ำหนัก (กิโลกรัม)",
          "number",
          r.weight ?? "",
          'required min="0.001" max="1000000" step="0.001"',
        ) +
        field(
          "record_date",
          "วันที่ส่ง",
          "date",
          r.record_date ||
            new Date(Date.now() + 7 * 3600000).toISOString().slice(0, 10),
          "required",
        ) +
        area("note", "หมายเหตุ", r.note);
    if (["records", "contents"].includes(kind))
      html +=
        field(
          "image",
          "ภาพ JPG / PNG / WEBP ไม่เกิน 3 MB",
          "file",
          "",
          'accept="image/jpeg,image/png,image/webp"',
        ) +
        (r.image
          ? `<img alt="ภาพประกอบรายการ" class="img-fluid rounded mt-3" style="max-height:240px" src="${api}image&kind=${kind}&id=${r.id}">`
          : "");
    if (kind === "reports")
      html +=
        area("message", "รายละเอียดปัญหา", r.message) +
        (r.id
          ? `<p class="mt-3">คำตอบ: ${esc(r.reply || "ยังไม่มีคำตอบ")}</p>`
          : "");
    const editable =
      !(kind === "contents" && !isStaff()) &&
      !(kind === "records" && r.status === "approved") &&
      !(kind === "reports" && r.id);
    $("#fields").html(html);
    $("#editForm button[type=submit]").toggle(editable);
    $("#fields input:not([type=hidden]),#fields select,#fields textarea").prop(
      "disabled",
      !editable,
    );
    if (r.id && !["users", "reports"].includes(kind) && editable)
      $("#fields").append(
        '<button type="button" class="btn btn-outline-danger mt-3" id="delete">ลบรายการนี้</button>',
      );
    if (kind === "records" && r.id && isStaff())
      $("#fields").append(
        `<hr><h3 class="fs-6">ตรวจรับรายการ</h3><p class="small muted">ผลตรวจใช้ข้อมูลที่บันทึกไว้ หากแก้ไขข้อมูลด้านบน ให้กดบันทึกและเปิดรายการอีกครั้งก่อนตรวจรับ</p>${select(
          "review_status",
          "ผลตรวจ",
          [
            ["pending", "รอตรวจ"],
            ["approved", "ตรวจรับแล้ว"],
            ["rejected", "ไม่ผ่าน"],
          ],
          r.status,
        )}${area("review_note", "เหตุผล / หมายเหตุ", r.review_note)}<button type="button" class="btn btn-primary mt-3" id="review">บันทึกผลตรวจ</button>`,
      );
    if (kind === "reports" && r.id && isStaff())
      $("#fields").append(
        `<hr>${area("reply", "ตอบกลับ", r.reply)}<button type="button" class="btn btn-primary mt-3" id="reply">ส่งคำตอบและทำเครื่องหมายอ่านแล้ว</button>`,
      );
    $("#editorTitle").text(
      (r.id ? "รายละเอียด / แก้ไข" : "เพิ่ม") + " " + titles[kind],
    );
    modal.show();
  }
  async function refresh() {
    modal.hide();
    table?.ajax.reload(null, false);
    options = await get("options");
  }
  $("#editForm").on("submit", async function (e) {
    e.preventDefault();
    const b = $(this).find("[type=submit]").prop("disabled", true);
    try {
      await post("save", new FormData(this));
      await refresh();
      notice("บันทึกเรียบร้อย");
    } catch (xhr) {
      error(xhr);
    } finally {
      b.prop("disabled", false);
    }
  });
  $("#fields").on("click", "#delete", async () => {
    if (!confirm("ยืนยันลบรายการนี้?")) return;
    try {
      await post("delete", {
        kind: activeKind,
        id: $("#fields [name=id]").val(),
      });
      await refresh();
      notice("ลบเรียบร้อย");
    } catch (xhr) {
      error(xhr);
    }
  });
  $("#fields").on("click", "#review", async () => {
    try {
      await post("review", {
        id: $("#fields [name=id]").val(),
        status: $("#fields [name=review_status]").val(),
        review_note: $("#fields [name=review_note]").val(),
      });
      await refresh();
      notice("บันทึกผลตรวจแล้ว");
    } catch (xhr) {
      error(xhr);
    }
  });
  $("#fields").on("click", "#reply", async () => {
    try {
      await post("reply", {
        id: $("#fields [name=id]").val(),
        reply: $("#fields [name=reply]").val(),
      });
      await refresh();
      notice("ตอบกลับแล้ว");
    } catch (xhr) {
      error(xhr);
    }
  });
  async function dashboard() {
    $("#app").html(
      `<div class="card p-4 mb-4"><h2 class="section-title">${isStaff() ? "ภาพรวมระบบ" : "ผลการรีไซเคิลของฉัน"}</h2>${filters()}<div id="metrics" class="row g-3"></div><div id="notifications" class="mt-3"></div><p class="muted small mt-3">ยอดน้ำหนักและมูลค่านับเฉพาะรายการที่ตรวจรับแล้ว · มูลค่าเป็นราคาประเมิน ณ วันที่บันทึก ไม่ใช่ยอดชำระเงิน</p><div class="row g-4"><div class="col-md-6 chart-wrap"><canvas id="typeChart" aria-label="น้ำหนักแยกประเภท"></canvas></div><div class="col-md-6 chart-wrap"><canvas id="monthChart" aria-label="น้ำหนักรายเดือน"></canvas></div></div><a class="btn btn-outline-success mt-3" id="dashExport">ส่งออกรายการตามตัวกรอง</a></div><div class="card p-4"><h2 class="section-title">เป้าหมายของฉันในเดือนปัจจุบัน</h2><p id="goalSummary"></p><form id="goalForm" class="row g-2"><div class="col-md-6">${field("target", "เป้าหมาย (กก.)", "number", "", 'required min="0.001" step="0.001"')}</div><div class="col-md-6 d-flex align-items-end"><button class="btn btn-success">บันทึกเป้าหมาย</button></div></form><p class="small muted mt-2">คะแนนสร้างแรงจูงใจ 10 คะแนนต่อกิโลกรัมที่ตรวจรับในเดือนนี้ ไม่ใช่สิทธิ์แลกเงิน</p></div>`,
    );
    let month;
    async function load() {
      try {
        const d = await get("dashboard", params());
        month = d.month;
        const metrics = [
          ["ตรวจรับแล้ว", Number(d.kg).toFixed(3) + " กก."],
          ["มูลค่าประเมิน", Number(d.value).toFixed(2) + " บาท"],
          ["รอตรวจ", d.pending + " รายการ"],
        ];
        if (isStaff()) {
          metrics.push([
            "สมาชิก / องค์กร",
            d.members + " / " + d.organizations,
          ]);
          $("#notifications").html(
            `<a href="?page=reports" class="text-success">แจ้งเตือน: ${esc(d.notifications)} รายงานรอคำตอบ</a> · สมาชิกใหม่วันนี้ ${esc(d.newUsers)} คน`,
          );
        }
        $("#metrics").html(
          metrics
            .map(
              ([a, b]) =>
                `<div class="col-sm-6 col-lg"><div class="p-3 bg-light rounded h-100"><div>${a}</div><div class="metric fs-4">${esc(b)}</div></div></div>`,
            )
            .join(""),
        );
        charts.forEach((c) => c.destroy());
        charts = [
          ["typeChart", d.byType, "bar"],
          ["monthChart", d.byMonth, "line"],
        ].map(
          ([id, data, type]) =>
            new Chart(document.getElementById(id), {
              type,
              data: {
                labels: Object.keys(data),
                datasets: [
                  {
                    label: "น้ำหนักที่ตรวจรับ (กก.)",
                    data: Object.values(data),
                    backgroundColor: "#7bab68",
                    borderColor: "#176b46",
                  },
                ],
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
              },
            }),
        );
        $("#goalSummary").text(
          `เดือน ${d.month}: ${Number(d.ownKg).toFixed(3)} / ${d.target} กก. • ${d.points} คะแนน • ${d.target > 0 ? Math.min(100, (d.ownKg / d.target) * 100).toFixed(1) : 0}%`,
        );
        $("#goalForm [name=target]").val(d.target || "");
        $("#dashExport").attr(
          "href",
          api +
            "export&" +
            $.param({ kind: "records", ...params(), status: "approved" }),
        );
      } catch (xhr) {
        error(xhr);
      }
    }
    $("#filters").on("submit", (e) => {
      e.preventDefault();
      load();
    });
    $("#goalForm").on("submit", async function (e) {
      e.preventDefault();
      try {
        await post("goal", {
          month,
          target: $(this).find("[name=target]").val(),
        });
        await load();
        notice("บันทึกเป้าหมายแล้ว");
      } catch (xhr) {
        error(xhr);
      }
    });
    await load();
  }
});
