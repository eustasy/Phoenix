/* Phoenix — Pwned Passwords advisory (assets/pwned-check.js).
 * Progressive enhancement on any input[data-pwned-check]: SHA-1 the value in the
 * browser (Web Crypto — secure context only), send ONLY the first 5 hex chars to
 * api.pwnedpasswords.com/range/ (HIBP k-anonymity), and match the remaining 35
 * chars locally. The password and its full hash never leave the browser, and the
 * server never sees either. Advisory only — the server still enforces the length
 * policy; this can only warn. Silently no-ops when crypto.subtle is unavailable
 * (insecure context / old browser) or on any network/parse failure. A plain GET
 * with no custom headers keeps it a simple CORS request (no preflight). */

;(function () {
  if (!window.crypto || !window.crypto.subtle || !window.fetch || !window.TextEncoder) return

  function toHex(buffer) {
    var out = ""
    var bytes = new Uint8Array(buffer)
    for (var i = 0; i < bytes.length; i++) {
      out += bytes[i].toString(16).padStart(2, "0")
    }
    return out.toUpperCase()
  }

  async function pwnedCount(password) {
    var digest = await crypto.subtle.digest("SHA-1", new TextEncoder().encode(password))
    var hash = toHex(digest)
    var prefix = hash.slice(0, 5)
    var suffix = hash.slice(5)
    var res = await fetch("https://api.pwnedpasswords.com/range/" + prefix)
    if (!res.ok) return -1
    var body = await res.text()
    var lines = body.split("\n")
    for (var i = 0; i < lines.length; i++) {
      var parts = lines[i].split(":")
      if (parts[0].trim().toUpperCase() === suffix) {
        return parseInt(parts[1], 10) || 0
      }
    }
    return 0
  }

  function attach(input) {
    var note = document.createElement("div")
    note.className = "ph-hint pwned-note"
    note.setAttribute("aria-live", "polite")
    note.hidden = true
    input.insertAdjacentElement("afterend", note)

    var timer = null
    var seq = 0
    input.addEventListener("input", function () {
      var value = input.value
      if (timer) clearTimeout(timer)
      if (!value) {
        note.hidden = true
        return
      }
      timer = setTimeout(function () {
        var mine = ++seq
        pwnedCount(value).then(
          function (count) {
            if (mine !== seq) return
            if (count > 0) {
              note.hidden = false
              note.className = "ph-hint pwned-note pwned-bad"
              note.textContent =
                "⚠ This password has appeared in " + count.toLocaleString() + " known data breaches. Choose a different one."
            } else if (count === 0) {
              note.hidden = false
              note.className = "ph-hint pwned-note pwned-ok"
              note.textContent = "Not found in known breaches."
            } else {
              note.hidden = true
            }
          },
          function () {
            note.hidden = true
          }
        )
      }, 500)
    })
  }

  function init() {
    var inputs = document.querySelectorAll("input[data-pwned-check]")
    for (var i = 0; i < inputs.length; i++) attach(inputs[i])
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init)
  } else {
    init()
  }
})()
