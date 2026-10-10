package ui

import (
	"fmt"
	"html/template"
	"net/http"
	"strings"
	"time"
)

// หน้าเดียวจบ ไม่มี asset ภายนอก เพราะเครื่องสาขาอาจไม่มีอินเทอร์เน็ตตอนตั้งค่า
var page = template.Must(template.New("p").Parse(`<!doctype html>
<html lang="th"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>เครื่องอ่านบัตร — exapp</title>
<style>
 body{font-family:-apple-system,"Segoe UI",sans-serif;background:#f3f4f6;margin:0;padding:24px;color:#111}
 .card{max-width:620px;margin:0 auto 16px;background:#fff;border-radius:10px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,.1)}
 h1{font-size:19px;margin:0 0 4px;color:#0e513a}
 .sub{color:#6b7280;font-size:13px;margin-bottom:18px}
 label{display:block;font-size:13px;color:#374151;margin:14px 0 4px}
 input[type=text],input[type=password]{width:100%;box-sizing:border-box;padding:9px 11px;border:1px solid #d1d5db;border-radius:6px;font-size:14px}
 button{padding:9px 18px;border:0;border-radius:6px;font-size:14px;cursor:pointer}
 .primary{background:#0e513a;color:#fff;font-weight:600}
 .ghost{background:#e5e7eb;color:#374151}
 .danger{background:#fff;color:#b91c1c;border:1px solid #fca5a5}
 .row{display:flex;gap:9px;align-items:center;margin-top:18px;flex-wrap:wrap}
 .dot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:8px;flex-shrink:0}
 .ok{background:#10b981}.bad{background:#ef4444}.idle{background:#9ca3af}
 .line{display:flex;align-items:center;padding:7px 0;font-size:14px;border-bottom:1px solid #f3f4f6}
 .line:last-child{border:0}
 .note{font-size:12px;color:#6b7280;margin-top:10px;line-height:1.6}
 .msg{padding:10px 12px;border-radius:6px;font-size:13px;margin-bottom:14px}
 .msg.good{background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46}
 .msg.err{background:#fef2f2;border:1px solid #fca5a5;color:#991b1b}
 code{background:#f3f4f6;padding:2px 5px;border-radius:4px;font-size:12px}
</style></head><body>

{{if .Message}}<div class="card" style="padding:0;box-shadow:none;background:none">
  <div class="msg {{.MessageKind}}">{{.Message}}</div></div>{{end}}

{{if .Setup}}
<div class="card">
  <h1>ตั้งค่าเครื่องอ่านบัตรครั้งแรก</h1>
  <div class="sub">ขอ token ได้จากหน้า ตั้งค่า → เครื่องอ่านบัตรประชาชน ในระบบ exapp</div>
  <form method="post" action="/save?k={{.Key}}">
    <label>ที่อยู่เซิร์ฟเวอร์</label>
    <input type="text" name="server_url" value="{{.ServerURL}}" placeholder="https://exapp.softernity.com" required>
    <label>token ประจำเครื่อง</label>
    <input type="password" name="token" placeholder="crd_..." required>
    <div class="row">
      <button class="primary" type="submit">ทดสอบและบันทึก</button>
    </div>
    <div class="note">
      ระบบจะลองต่อกับเซิร์ฟเวอร์ก่อนบันทึก ถ้าต่อไม่ได้จะไม่บันทึกให้
      เพื่อไม่ให้เครื่องรันอยู่เงียบ ๆ โดยที่ไม่มีใครรู้ว่าตั้งค่าผิด
    </div>
  </form>
</div>
{{else}}
<div class="card">
  <h1>เครื่องอ่านบัตร</h1>
  <div class="sub">รุ่น {{.Version}}{{if .CounterName}} · {{.CounterName}}{{end}}</div>

  <div class="line"><span class="dot {{if .ReaderFound}}ok{{else}}bad{{end}}"></span>
    {{if .ReaderFound}}พบเครื่องอ่านบัตร{{else}}ไม่พบเครื่องอ่านบัตร — ตรวจสาย{{end}}</div>

  <div class="line"><span class="dot {{if .ServerOk}}ok{{else}}bad{{end}}"></span>
    {{if .ServerOk}}ต่อกับระบบได้ ({{.LastBeatText}}){{else}}ต่อกับระบบไม่ได้{{if .LastError}} — {{.LastError}}{{end}}{{end}}</div>

  <div class="line"><span class="dot idle"></span>{{.ServerURL}}</div>

  {{range .ReaderNames}}<div class="line" style="color:#6b7280;font-size:13px">
    <span class="dot idle"></span>{{.}}</div>{{end}}

  <div class="row">
    <form method="post" action="/autostart?k={{.Key}}" style="margin:0">
      <input type="hidden" name="on" value="{{if .AutoStart}}0{{else}}1{{end}}">
      <button class="ghost" type="submit">{{if .AutoStart}}ไม่ต้องเปิดเองตอนบูต{{else}}เปิดเองตอนบูตเครื่อง{{end}}</button>
    </form>
    <form method="post" action="/update?k={{.Key}}" style="margin:0">
      <button class="ghost" type="submit">ตรวจรุ่นใหม่</button>
    </form>
    <a href="/?setup=1&k={{.Key}}"><button class="ghost" type="button">แก้ไขการตั้งค่า</button></a>
    <form method="post" action="/quit?k={{.Key}}" style="margin:0"
          onsubmit="return confirm('ปิดโปรแกรม? เครื่องอ่านบัตรจะไม่ทำงานจนกว่าจะเปิดใหม่')">
      <button class="danger" type="submit">ปิดโปรแกรม</button>
    </form>
  </div>

  <div class="note">
    <b>ปิดหน้าต่างนี้ไม่ได้ปิดโปรแกรม</b> — มันจะทำงานเบื้องหลังต่อ
    ซึ่งเป็นสิ่งที่ต้องการ เปิดกลับมาดูได้โดยดับเบิลคลิกที่ตัวโปรแกรมอีกครั้ง
    ถ้าจะหยุดจริง ๆ ให้กดปุ่มปิดโปรแกรม
  </div>
</div>
{{end}}

</body></html>`))

type view struct {
	Setup        bool
	Key          string
	Message      string
	MessageKind  string
	ServerURL    string
	Version      string
	CounterName  string
	ReaderFound  bool
	ReaderNames  []string
	ServerOk     bool
	LastBeatText string
	LastError    string
	AutoStart    bool
}

func (s *Server) render(w http.ResponseWriter, r *http.Request, msg, kind string) {
	st := s.snapshot()

	v := view{
		Setup:       !st.Configured || r.URL.Query().Get("setup") == "1",
		Key:         s.secret,
		Message:     msg,
		MessageKind: kind,
		ServerURL:   st.ServerURL,
		Version:     st.Version,
		CounterName: st.CounterName,
		ReaderFound: st.ReaderFound,
		ReaderNames: st.ReaderNames,
		LastError:   st.LastError,
		AutoStart:   st.AutoStart,
	}

	// ได้ยินจากเซิร์ฟเวอร์ภายในสองนาทีถือว่าต่อติด — เท่ากับเกณฑ์ที่ฝั่งเซิร์ฟเวอร์ใช้
	if !st.LastBeat.IsZero() && time.Since(st.LastBeat) < 2*time.Minute {
		v.ServerOk = true
		v.LastBeatText = "ล่าสุด " + st.LastBeat.Format("15:04:05")
	}

	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	_ = page.Execute(w, v)
}

func (s *Server) handlePage(w http.ResponseWriter, r *http.Request) {
	s.render(w, r, "", "")
}

func (s *Server) handleSave(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Redirect(w, r, "/?k="+s.secret, http.StatusSeeOther)

		return
	}

	url := trimmed(r.FormValue("server_url"))
	token := trimmed(r.FormValue("token"))

	/*
	 * ลองต่อก่อนบันทึกเสมอ
	 *
	 * ถ้าบันทึกค่าที่ผิดไว้ โปรแกรมจะรันอยู่เงียบ ๆ โดยไม่มีใครรู้ว่าตั้งค่าผิด
	 * จนกว่าจะมีลูกค้ายืนรอหน้าเคาน์เตอร์แล้วเสียบบัตรไม่ขึ้น
	 */
	if err := s.deps.TestConnection(url, token); err != nil {
		s.render(w, r, "ต่อกับเซิร์ฟเวอร์ไม่ได้: "+err.Error(), "err")

		return
	}

	if err := s.deps.Save(url, token); err != nil {
		s.render(w, r, "บันทึกไม่สำเร็จ: "+err.Error(), "err")

		return
	}

	s.SetState(func(st *State) {
		st.Configured = true
		st.ServerURL = url
	})

	s.render(w, r, "บันทึกแล้ว เครื่องพร้อมใช้งาน", "good")
}

func (s *Server) handleTest(w http.ResponseWriter, r *http.Request) {
	err := s.deps.TestConnection(trimmed(r.FormValue("server_url")), trimmed(r.FormValue("token")))
	if err != nil {
		s.render(w, r, "ต่อไม่ได้: "+err.Error(), "err")

		return
	}

	s.render(w, r, "ต่อกับเซิร์ฟเวอร์ได้", "good")
}

func (s *Server) handleAutoStart(w http.ResponseWriter, r *http.Request) {
	on := r.FormValue("on") == "1"

	if err := s.deps.SetAutoStart(on); err != nil {
		s.render(w, r, "ตั้งค่าไม่สำเร็จ: "+err.Error(), "err")

		return
	}

	s.SetState(func(st *State) { st.AutoStart = on })

	if on {
		s.render(w, r, "จะเปิดโปรแกรมนี้เองทุกครั้งที่เปิดเครื่อง", "good")

		return
	}

	s.render(w, r, "เลิกเปิดเองตอนบูตแล้ว", "good")
}

func (s *Server) handleQuit(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	_, _ = w.Write([]byte(`<!doctype html><html lang="th"><head><meta charset="utf-8">
<title>ปิดแล้ว</title><style>body{font-family:-apple-system,"Segoe UI",sans-serif;
background:#f3f4f6;padding:40px;text-align:center;color:#374151}
.c{max-width:420px;margin:0 auto;background:#fff;border-radius:10px;padding:28px}
h1{font-size:18px;color:#0e513a;margin:0 0 8px}p{font-size:14px;line-height:1.7;margin:0}
</style></head><body><div class="c">
<h1>ปิดโปรแกรมแล้ว</h1>
<p>เครื่องอ่านบัตรจะไม่ทำงานจนกว่าจะเปิดโปรแกรมใหม่<br>ปิดหน้าต่างนี้ได้เลย</p>
</div></body></html>`))

	// ตอบให้เบราว์เซอร์เห็นก่อน แล้วค่อยปิด ไม่งั้นผู้ใช้จะเจอหน้าว่าง
	// แล้วไม่แน่ใจว่าปิดสำเร็จหรือโปรแกรมพัง
	if f, ok := w.(http.Flusher); ok {
		f.Flush()
	}

	go func() {
		time.Sleep(300 * time.Millisecond)
		s.deps.Quit()
	}()
}

func (s *Server) handleUpdate(w http.ResponseWriter, r *http.Request) {
	version, available, err := s.deps.CheckUpdate()
	if err != nil {
		s.render(w, r, "ตรวจรุ่นใหม่ไม่สำเร็จ: "+err.Error(), "err")

		return
	}

	if !available {
		s.render(w, r, "ใช้รุ่นล่าสุดอยู่แล้ว", "good")

		return
	}

	if err := s.deps.ApplyUpdate(); err != nil {
		s.render(w, r, "อัปเดตไม่สำเร็จ: "+err.Error(), "err")

		return
	}

	s.render(w, r, fmt.Sprintf("อัปเดตเป็นรุ่น %s แล้ว ปิดแล้วเปิดโปรแกรมใหม่", strings.TrimPrefix(version, "v")), "good")
}
