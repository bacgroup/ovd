/*
 * Copyright (C) 2011 Ulteo SAS
 * http://www.ulteo.com
 * Author Thomas MOUTON <thomas@ulteo.com> 2011
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2
 * of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

#include "windowUtil.h"

#include "assert.h"

BOOL WindowUtil_isToplevel(HWND hwnd) {
	BOOL toplevel;
	HWND parent;
	parent = GetAncestor(hwnd, GA_PARENT);

	/* According to MS: "A window that has no parent, or whose
	   parent is the desktop window, is called a top-level
	   window." See http://msdn2.microsoft.com/en-us/library/ms632597(VS.85).aspx. */
	toplevel = (!parent || parent == GetDesktopWindow());
	return toplevel;
}

HWND WindowUtil_getParent(HWND hwnd) {
	HWND result;
	HWND owner;
	LONG exstyle;

	/* Use the same logic to determine if the window should be
	   "transient" (ie have no task icon) as MS uses. This is documented at 
	   http://msdn2.microsoft.com/en-us/library/bb776822.aspx */
	owner = GetWindow(hwnd, GW_OWNER);
	exstyle = GetWindowLong(hwnd, GWL_EXSTYLE);
	if (!owner && !(exstyle & WS_EX_TOOLWINDOW))
	{
		/* display taskbar icon */
		result = NULL;
	}
	else
	{
		/* no taskbar icon */
		if (owner)
			result = owner;
		else
			result = (HWND) - 1;
	}

	return result;
}

BOOL WindowUtil_setFocus(HWND hwnd) {
	BOOL ret;

	// Attach foreground window thread
	AttachThreadInput(GetWindowThreadProcessId(GetForegroundWindow(), NULL), GetCurrentThreadId(), TRUE);

	ret = SetForegroundWindow(hwnd);
	SetFocus(hwnd);

	// Detach the attached thread
	AttachThreadInput(GetWindowThreadProcessId(GetForegroundWindow(), NULL), GetCurrentThreadId(), FALSE);

	return ret;
}

int WindowUtil_getState(HWND hwnd) {
	if (IsZoomed(hwnd))
		return 2;
	else if (IsIconic(hwnd))
		return 1;
	else
		return 0;
}

BOOL WindowUtil_setState(HWND hwnd, int state) {
	if (state == 0)
		ShowWindow(hwnd, SW_RESTORE);
	else if (state == 1)
		ShowWindow(hwnd, SW_MINIMIZE);
	else if (state == 2)
		ShowWindow(hwnd, SW_MAXIMIZE);
	else
		return FALSE;

	return TRUE;
}

HICON WindowUtil_getIcon(HWND hwnd, int large) {
	HICON icon;

	if (!SendMessageTimeout(hwnd, WM_GETICON, large ? ICON_BIG : ICON_SMALL,
				0, SMTO_ABORTIFHUNG, 1000, (PDWORD_PTR) & icon))
		return NULL;

	if (icon)
		return icon;

	/*
	 * Modern versions of Windows uses the voodoo value of 2 instead of 0
	 * for the small icons.
	 */
	if (!large)
	{
		if (!SendMessageTimeout(hwnd, WM_GETICON, 2,
					0, SMTO_ABORTIFHUNG, 1000, (PDWORD_PTR) & icon))
			return NULL;
	}

	if (icon)
		return icon;

	icon = (HICON) GetClassLong(hwnd, large ? GCL_HICON : GCL_HICONSM);

	if (icon)
		return icon;

	return NULL;
}

int WindowUtil_extractIcon(HICON icon, char *buffer, int maxlen) {
	ICONINFO info;
	HDC hdc;
	BITMAP mask_bmp, color_bmp;
	BITMAPINFO bmi;
	int size, i;
	char *mask_buf, *color_buf;
	char *o, *m, *c;
	int ret = -1;

	assert(buffer);
	assert(maxlen > 0);

	if (!GetIconInfo(icon, &info))
		goto fail;

	if (!GetObject(info.hbmMask, sizeof(BITMAP), &mask_bmp))
		goto free_bmps;
	if (!GetObject(info.hbmColor, sizeof(BITMAP), &color_bmp))
		goto free_bmps;

	if (mask_bmp.bmWidth != color_bmp.bmWidth)
		goto free_bmps;
	if (mask_bmp.bmHeight != color_bmp.bmHeight)
		goto free_bmps;

	if ((mask_bmp.bmWidth * mask_bmp.bmHeight * 4) > maxlen)
		goto free_bmps;

	size = (mask_bmp.bmWidth + 3) / 4 * 4;
	size *= mask_bmp.bmHeight;
	size *= 4;

	mask_buf = malloc(size);
	if (!mask_buf)
		goto free_bmps;
	color_buf = malloc(size);
	if (!color_buf)
		goto free_mbuf;

	memset(&bmi, 0, sizeof(BITMAPINFO));

	bmi.bmiHeader.biSize = sizeof(BITMAPINFO);
	bmi.bmiHeader.biWidth = mask_bmp.bmWidth;
	bmi.bmiHeader.biHeight = -mask_bmp.bmHeight;
	bmi.bmiHeader.biPlanes = 1;
	bmi.bmiHeader.biBitCount = 32;
	bmi.bmiHeader.biCompression = BI_RGB;
	bmi.bmiHeader.biSizeImage = size;

	hdc = CreateCompatibleDC(NULL);
	if (!hdc)
		goto free_cbuf;

	if (!GetDIBits(hdc, info.hbmMask, 0, mask_bmp.bmHeight, mask_buf, &bmi, DIB_RGB_COLORS))
		goto del_dc;
	if (!GetDIBits(hdc, info.hbmColor, 0, color_bmp.bmHeight, color_buf, &bmi, DIB_RGB_COLORS))
		goto del_dc;

	o = buffer;
	m = mask_buf;
	c = color_buf;
	for (i = 0; i < size / 4; i++)
	{
		o[0] = c[2];
		o[1] = c[1];
		o[2] = c[0];

		o[3] = ((int) (unsigned char) m[0] + (unsigned char) m[1] +
			(unsigned char) m[2]) / 3;
		o[3] = 0xff - o[3];

		o += 4;
		m += 4;
		c += 4;
	}

	ret = size;

      del_dc:
	DeleteDC(hdc);

      free_cbuf:
	free(color_buf);
      free_mbuf:
	free(mask_buf);

      free_bmps:
	DeleteObject(info.hbmMask);
	DeleteObject(info.hbmColor);

      fail:
	return ret;
}