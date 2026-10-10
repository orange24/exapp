//go:build windows

package autostart

import (
	"golang.org/x/sys/windows/registry"
)

// Windows ใช้ registry key ของผู้ใช้ ไม่ใช่ของเครื่อง
//
// HKCU ไม่ต้องขอสิทธิ์ผู้ดูแล ซึ่งสำคัญเพราะพนักงานที่สาขามักไม่ใช่ admin
// ของเครื่องตัวเอง
const runKey = `Software\Microsoft\Windows\CurrentVersion\Run`

func enabled() bool {
	k, err := registry.OpenKey(registry.CURRENT_USER, runKey, registry.QUERY_VALUE)
	if err != nil {
		return false
	}
	defer k.Close()

	_, _, err = k.GetStringValue(Name)

	return err == nil
}

func enable(exe string) error {
	k, _, err := registry.CreateKey(registry.CURRENT_USER, runKey, registry.SET_VALUE)
	if err != nil {
		return err
	}
	defer k.Close()

	return k.SetStringValue(Name, `"`+exe+`" -background`)
}

func disable() error {
	k, err := registry.OpenKey(registry.CURRENT_USER, runKey, registry.SET_VALUE)
	if err != nil {
		return nil
	}
	defer k.Close()

	if err := k.DeleteValue(Name); err != nil && err != registry.ErrNotExist {
		return err
	}

	return nil
}
