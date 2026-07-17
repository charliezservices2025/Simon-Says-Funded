#!/usr/bin/env python3
"""Refined, pixel-faithful fillable AHA BLS/ACLS Course Completion receipt for
Nura Care Institute (Tynesha Zacarias). Matches the approved design image.

- License number 07150349010 is drawn as STATIC centered baseline text (never a
  form field) so it is always present and can never silently overflow.
- Everything else the instructor fills is an AcroForm field (text + checkboxes).
- Shapes commit per call so paint order matches call order (fills under text).
"""
import fitz

NAVY  = (0.094, 0.165, 0.353)
RED   = (0.800, 0.130, 0.180)
INK   = (0.110, 0.130, 0.160)
LINE  = (0.34, 0.42, 0.56)
LIGHT = (0.87, 0.89, 0.92)
WHITE = (1, 1, 1)

W, H = 612, 792
doc = fitz.open()
doc.set_metadata({"title": "AHA BLS/ACLS Course Completion Receipt - Nura Care Institute",
                  "author": "Nura Care Institute", "subject": "Course completion receipt"})
page = doc.new_page(width=W, height=H)
M = 34
RB = W - M
widgets = []


# ---------- primitives ----------
def fill_rect(r, color, border=None, width=1.0, radius=None):
    s = page.new_shape(); s.draw_rect(fitz.Rect(*r), radius=radius) if radius else s.draw_rect(fitz.Rect(*r))
    s.finish(fill=color, color=border or color, width=width); s.commit()

def stroke_rect(r, color, width=1.0, radius=None):
    s = page.new_shape(); s.draw_rect(fitz.Rect(*r), radius=radius) if radius else s.draw_rect(fitz.Rect(*r))
    s.finish(fill=None, color=color, width=width); s.commit()

def line(p1, p2, color, width=1.0):
    s = page.new_shape(); s.draw_line(fitz.Point(*p1), fitz.Point(*p2)); s.finish(color=color, width=width); s.commit()

def circle(pt, r, color, fill=True, width=1.0):
    s = page.new_shape(); s.draw_circle(fitz.Point(*pt), r)
    s.finish(fill=color if fill else None, color=color, width=width); s.commit()

def poly(pts, color, width=1.0, fill=None, close=False, cap=0):
    s = page.new_shape(); s.draw_polyline([fitz.Point(*p) for p in pts])
    s.finish(fill=fill, color=color, width=width, closePath=close, lineCap=cap, lineJoin=1); s.commit()

def bezier(p0, c0, c1, p1, color, width=1.0, cap=1):
    s = page.new_shape(); s.draw_bezier(fitz.Point(*p0), fitz.Point(*c0), fitz.Point(*c1), fitz.Point(*p1))
    s.finish(color=color, width=width, lineCap=cap); s.commit()

def T(x, y, s, size=10, color=INK, font="helv"):
    page.insert_text((x, y), s, fontsize=size, fontname=font, color=color)

def TB(r, s, size=10, color=INK, font="helv", align=0):
    page.insert_textbox(fitz.Rect(*r), s, fontsize=size, fontname=font, color=color, align=align)

def tw(s, font, size):
    return fitz.get_text_length(s, fontname=font, fontsize=size)

def TC(x0, x1, y, s, size=10, color=INK, font="helv"):
    """Centered baseline text between x0..x1 (never silently overflows)."""
    T(x0 + (x1 - x0 - tw(s, font, size)) / 2, y, s, size=size, color=color, font=font)


# ---------- icons ----------
def heart(cx, cy, s, color, pulse=WHITE):
    r = s * 0.28
    circle((cx - r, cy - r * 0.4), r, color)
    circle((cx + r, cy - r * 0.4), r, color)
    poly([(cx - 2 * r, cy - r * 0.15), (cx + 2 * r, cy - r * 0.15), (cx, cy + r * 1.6)], color, fill=color, close=True)
    if pulse:
        y0 = cy - r * 0.05
        poly([(cx - 1.9 * r, y0), (cx - r, y0), (cx - r * 0.45, y0 - r), (cx, y0 + r),
              (cx + r * 0.5, y0), (cx + 1.9 * r, y0)], pulse, width=max(1.0, s * 0.05))

def ekg(x, y, w, color, width=1.4):
    poly([(x, y), (x + .18 * w, y), (x + .30 * w, y - 6.5), (x + .42 * w, y + 8),
          (x + .54 * w, y - 9), (x + .64 * w, y), (x + w, y)], color, width=width, cap=1)

def pin(cx, cy, s, color, dot=WHITE):
    circle((cx, cy), s * 0.55, color)
    poly([(cx - s * 0.5, cy + s * 0.18), (cx + s * 0.5, cy + s * 0.18), (cx, cy + s * 1.15)], color, fill=color, close=True)
    circle((cx, cy - s * 0.03), s * 0.2, dot)

def phone(cx, cy, s, color):
    circle((cx, cy), s, color, fill=False, width=1.3)
    e = s * 0.42
    circle((cx - e * 0.7, cy - e * 0.7), s * 0.17, color)
    circle((cx + e * 0.7, cy + e * 0.7), s * 0.17, color)
    line((cx - e * 0.7, cy - e * 0.7), (cx + e * 0.7, cy + e * 0.7), color, width=s * 0.34)


# ---------- widget builders ----------
def add_text(name, rect, size=11, prefill="", color=INK):
    wd = fitz.Widget()
    wd.field_name = name
    wd.field_type = fitz.PDF_WIDGET_TYPE_TEXT
    wd.rect = fitz.Rect(*rect)
    wd.text_fontsize = size
    wd.text_color = color
    wd.border_width = 0
    wd.fill_color = None
    if prefill:
        wd.field_value = prefill
    widgets.append(wd)

def field(x, y, w, name, size=11, prefill="", label=None, label_w=0, lsize=9.5):
    lx = x
    if label:
        T(x, y, label, size=lsize, font="hebo", color=NAVY)
        lx = x + label_w
    line((lx, y + 3), (x + w, y + 3), LINE, 1)
    add_text(name, (lx + 2, y - 10, x + w, y + 2), size=size, prefill=prefill)

def checkbox(x, y, name, size=12):
    wd = fitz.Widget()
    wd.field_name = name
    wd.field_type = fitz.PDF_WIDGET_TYPE_CHECKBOX
    wd.rect = fitz.Rect(x, y, x + size, y + size)
    wd.border_width = 1.2
    wd.border_color = NAVY
    wd.fill_color = WHITE
    widgets.append(wd)
    return x + size

def chip(x, y, label, w=None, h=19):
    bw = (w if w else tw(label, "hebo", 10) + 22)
    fill_rect((x, y, x + bw, y + h), NAVY, radius=(0.10, 0.34))
    T(x + 11, y + h - 5.5, label, size=10, font="hebo", color=WHITE)
    return y + h


# ================= HEADER =================
heart(M + 15, 58, 27, RED)
T(M + 46, 53, "TYNESHA ZACARIAS", size=17, font="hebo", color=NAVY)
T(M + 46, 68, "TRAINING THAT EMPOWERS YOU TO", size=8.6, font="hebo", color=RED)
T(M + 46, 79, "MAKE A DIFFERENCE.", size=8.6, font="hebo", color=RED)
ekg(M + 46 + tw("MAKE A DIFFERENCE.", "hebo", 8.6) + 8, 76, 66, RED, 1.5)
# right block
line((372, 38), (372, 84), LIGHT, 1.4)
TC(388, RB, 50, "LICENSE NUMBER", size=9.5, font="hebo", color=NAVY)
stroke_rect((388, 57, RB, 83), NAVY, 1.4, radius=(0.05, 0.30))
TC(388, RB, 76, "07150349010", size=15.5, font="hebo", color=NAVY)   # STATIC, always present

# ================= BANNER =================
by = 94
fill_rect((M, by, RB, by + 54), NAVY, radius=(0.05, 0.26))
# AHA mark
fill_rect((M + 13, by + 12, M + 45, by + 44), RED, radius=(0.14, 0.14))
heart(M + 29, by + 28, 19, WHITE, pulse=None)
T(M + 51, by + 21, "American", size=8, font="hebo", color=WHITE)
T(M + 51, by + 31, "Heart", size=8, font="hebo", color=WHITE)
T(M + 51, by + 41, "Association.", size=8, font="hebo", color=WHITE)
TC(M + 120, RB - 150, by + 38, "RECEIPT", size=29, font="hebo", color=WHITE)
TB((RB - 172, by + 15, RB - 12, by + 49), "AHA ACLS or BLS\nCOURSE COMPLETION", size=9.5, font="hebo", color=WHITE, align=2)

# ============ RECEIPT NO / DATE ============
y = 172
field(M, y, 252, "receipt_number", label="RECEIPT NUMBER:", label_w=104)
field(W - 252, y, 252, "receipt_date", label="DATE:", label_w=42)

# ============ STUDENT INFORMATION ============
y = 190
chip(M, y, "STUDENT INFORMATION")
y += 29
field(M, y, RB - M, "student_name", label="STUDENT NAME:", label_w=104)
y += 25
field(M, y, RB - M, "student_phone", label="PHONE NUMBER:", label_w=104)
y += 25
field(M, y, RB - M, "student_email", label="EMAIL:", label_w=104)
y += 15
line((M, y), (RB, y), LIGHT, 1)

# ============ COURSE INFORMATION ============
y += 13
chip(M, y, "COURSE INFORMATION")
y += 27
T(M, y, "COURSE COMPLETED (CHECK ONE):", size=9.5, font="hebo", color=NAVY)
y += 15
cx = checkbox(M, y, "course_bls")
T(cx + 9, y + 10, "AHA BLS", size=11, font="hebo", color=INK)
cx2 = checkbox(M + 165, y, "course_acls")
T(cx2 + 9, y + 10, "AHA ACLS", size=11, font="hebo", color=INK)
y += 27
field(M, y, 320, "course_date", label="COURSE DATE:", label_w=92)
y += 25
field(M, y, 340, "instructor_name", label="INSTRUCTOR NAME:", label_w=118)
y += 15
line((M, y), (RB, y), LIGHT, 1)

# ============ PAYMENT INFORMATION ============
y += 13
chip(M, y, "PAYMENT INFORMATION")
y += 24
tbl_top = y
col2 = M + 214
col3 = M + 360
fill_rect((M, y, RB, y + 21), NAVY)
TC(M, col2, y + 14.5, "DESCRIPTION", size=9, font="hebo", color=WHITE)
TC(col2, col3, y + 14.5, "AMOUNT", size=9, font="hebo", color=WHITE)
TC(col3, RB, y + 14.5, "PAYMENT METHOD", size=9, font="hebo", color=WHITE)
y += 21
rowH = 23
desc_rows = [("pay_bls", "AHA BLS Course"), ("pay_acls", "AHA ACLS Course")]
method_rows = [("m_cash", "CASH"), ("m_card", "CREDIT/DEBIT CARD"), ("m_zelle", "ZELLE")]
for i in range(3):
    ry = y + i * rowH
    if i < 2:
        name, lab = desc_rows[i]
        bx = checkbox(M + 8, ry + 6, name, size=11)
        T(bx + 7, ry + 15, lab, size=10, font="helv", color=INK)
    else:
        T(M + 8, ry + 15, "Other:", size=9.5, font="hebo", color=NAVY)
        field(M + 46, ry + 12, col2 - (M + 46) - 8, "pay_other_desc", size=9)
    T(col2 + 10, ry + 15, "$", size=11, font="hebo", color=INK)
    field(col2 + 20, ry + 12, col3 - (col2 + 20) - 10, "pay_amt_%d" % i, size=10)
    mname, mlab = method_rows[i]
    mx = checkbox(col3 + 10, ry + 6, mname, size=11)
    T(mx + 7, ry + 15, mlab, size=9.5, font="helv", color=INK)
    line((M, ry + rowH), (RB, ry + rowH), LIGHT, 0.8)
ry = y + 3 * rowH
mx = checkbox(col3 + 10, ry + 6, "m_other", size=11)
T(mx + 7, ry + 15, "OTHER:", size=9.5, font="helv", color=INK)
field(col3 + 62, ry + 12, RB - (col3 + 62) - 6, "m_other_desc", size=9)
for cxl in (col2, col3):
    line((cxl, tbl_top + 21), (cxl, ry + rowH), LIGHT, 0.8)
stroke_rect((M, tbl_top, RB, ry + rowH), LINE, 1)
y = ry + rowH
stroke_rect((M, y, RB, y + 25), LINE, 1)
T(M + 10, y + 17, "TOTAL AMOUNT PAID:", size=11, font="hebo", color=NAVY)
T(col3 + 10, y + 17, "$", size=12, font="hebo", color=INK)
field(col3 + 22, y + 14, RB - (col3 + 22) - 8, "total_paid", size=11)

# ============ BOTTOM: received (left) | school + signature (right) ============
y += 38
left_w = 296
div_x = M + left_w + 14
rx = div_x + 16
line((div_x, y - 8), (div_x, y + 108), LIGHT, 1.2)   # vertical divider

# right column
ry0 = y - 8
chip(rx, ry0, "SCHOOL / TRAINING LOCATION", w=RB - rx)
pin(rx + 8, ry0 + 39, 8, NAVY)
T(rx + 24, ry0 + 34, "9198 Greenback Lane", size=10, font="helv", color=INK)
T(rx + 24, ry0 + 46, "Suite 108", size=10, font="helv", color=INK)
T(rx + 24, ry0 + 58, "Orangevale, CA 95662", size=10, font="helv", color=INK)
sy = ry0 + 75
T(rx, sy, "INSTRUCTOR SIGNATURE:", size=9, font="hebo", color=NAVY)
line((rx, sy + 21), (RB, sy + 21), LINE, 1)
T(rx + 8, sy + 17, "Tynesha Zacarias", size=16, font="tiit", color=NAVY)   # static signature
T(rx, sy + 38, "DATE:", size=9, font="hebo", color=NAVY)
line((rx + 38, sy + 39), (RB, sy + 39), LINE, 1)
add_text("sig_date", (rx + 40, sy + 28, RB, sy + 41), size=10)

# left column
field(M, y, left_w, "received_from", label="RECEIVED FROM:", label_w=104)
y += 23
T(M, y, "THE AMOUNT OF:", size=9.5, font="hebo", color=NAVY)
T(M + 100, y, "$", size=11, font="hebo", color=INK)
field(M + 110, y, left_w - 110, "amount_of", size=10)
y += 23
T(M, y, "FOR:", size=9.5, font="hebo", color=NAVY)
T(M + 34, y, "AHA", size=9.5, font="hebo", color=INK)
field(M + 60, y, 44, "for_course", size=9)
T(M + 112, y, "(BLS / ACLS) COURSE COMPLETION", size=8.5, font="hebo", color=INK)
y += 22
T(M, y, "NOTES:", size=9.5, font="hebo", color=NAVY)
field(M + 46, y, left_w - 46, "notes_1", size=9)
y += 18
field(M, y, left_w, "notes_2", size=9)

# ================= FOOTER =================
fy = 710
TC(M, RB, fy, "Thank you for choosing quality training and continuing your education!",
   size=13, font="tiit", color=RED)
_msg = "CONTINUING EDUCATION TODAY FOR A BETTER TOMORROW."
_mw = tw(_msg, "hebo", 9.5)
_mx = (M + RB) / 2 - _mw / 2
T(_mx, fy + 20, _msg, size=9.5, font="hebo", color=NAVY)
ekg(_mx - 62, fy + 16, 50, RED, 1.4)
ekg(_mx + _mw + 12, fy + 16, 50, RED, 1.4)

fby = 752
fill_rect((0, fby, W, H), NAVY)
midy = fby + (H - fby) / 2
phone(30, midy, 9, WHITE)
T(46, midy + 3.5, "916 544 1256", size=9.5, font="hebo", color=WHITE)
pin(214, midy - 2, 8, WHITE)
T(228, midy - 2, "9198 Greenback Lane, Suite 108", size=8.3, font="helv", color=WHITE)
T(228, midy + 9, "Orangevale, CA 95662", size=8.3, font="helv", color=WHITE)
heart(416, midy, 15, WHITE, pulse=None)
TB((432, fby + 6, W - 14, H - 4), "TRAINING THAT EMPOWERS YOU TO MAKE A DIFFERENCE.",
   size=7.6, font="hebo", color=WHITE, align=2)

# ---------- finalize ----------
for wd in widgets:
    page.add_widget(wd)
try:
    doc.set_need_appearances(True)
except Exception:
    pass

out = "nura-care-institute/data/forms/aha-course-receipt.pdf"
doc.save(out, deflate=True, garbage=4)
print("saved", out, "| fields:", len(widgets))
