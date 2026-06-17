/* Phoenix — magnet generator (magnet.php).
 * Inlined by PHP inside a <script> tag, prefixed with `const ANNOUNCE = "…";`
 * (the local announce URL). Parsing is delegated to PhoenixTorrent
 * (assets/torrent-parse.js); this file owns the magnet-specific field
 * extraction, link building, and the page UI. */
/* global ANNOUNCE, PhoenixTorrent, phInitIcons */

;(function () {
  const decode = PhoenixTorrent.decode

  // Magnet-specific extraction: prepend the local ANNOUNCE, and collect web
  // seeds as both ws (web seed) and xs (exact source).
  function extractInfo(torrent) {
    const info = torrent.info || {}
    const nameBytes = info["name.utf-8"] || info["name"]
    const name = nameBytes instanceof Uint8Array ? decode(nameBytes) : ""
    let size = 0
    if (typeof info.length === "number") size = info.length
    else if (Array.isArray(info.files)) for (const f of info.files) if (typeof f.length === "number") size += f.length
    const trackers = [ANNOUNCE]
    const add = (url) => {
      if (url && !trackers.includes(url)) trackers.push(url)
    }
    if (torrent.announce) add(decode(torrent.announce))
    if (Array.isArray(torrent["announce-list"]))
      for (const tier of torrent["announce-list"]) if (Array.isArray(tier)) for (const u of tier) add(decode(u))
    const webSeeds = [],
      exactSources = []
    const addSeed = (u) => {
      const s = decode(u)
      if (s) {
        webSeeds.push(s)
        exactSources.push(s)
      }
    }
    if (torrent["url-list"]) {
      const ul = torrent["url-list"]
      if (ul instanceof Uint8Array) addSeed(ul)
      else if (Array.isArray(ul)) for (const u of ul) addSeed(u)
    }
    return { name, size, trackers, webSeeds, exactSources }
  }
  function buildMagnet(hash, name, size, trackers, webSeeds, exactSources, acceptableSources, keywords) {
    let m = "magnet:?xt=urn:btih:" + hash
    if (name) m += "&dn=" + encodeURIComponent(name)
    if (size) m += "&xl=" + size
    for (const tr of trackers) if (tr.trim()) m += "&tr=" + encodeURIComponent(tr.trim())
    for (const ws of webSeeds) if (ws.trim()) m += "&ws=" + encodeURIComponent(ws.trim())
    for (const xs of exactSources) if (xs.trim()) m += "&xs=" + encodeURIComponent(xs.trim())
    for (const as of acceptableSources) if (as.trim()) m += "&as=" + encodeURIComponent(as.trim())
    if (keywords.trim()) m += "&kt=" + encodeURIComponent(keywords.trim())
    return m
  }

  const dropZone = document.getElementById("drop-zone"),
    fileInput = document.getElementById("file-input")
  const errorEl = document.getElementById("error"),
    errorText = document.getElementById("error-text"),
    results = document.getElementById("results")
  const fXt = document.getElementById("f-xt"),
    fDn = document.getElementById("f-dn"),
    fXl = document.getElementById("f-xl")
  const fTr = document.getElementById("f-tr"),
    fWs = document.getElementById("f-ws"),
    fXs = document.getElementById("f-xs")
  const fAs = document.getElementById("f-as"),
    fKt = document.getElementById("f-kt")
  const magnetOut = document.getElementById("magnet-out"),
    copyBtn = document.getElementById("copy-btn")

  function showError(msg) {
    errorText.textContent = msg
    errorEl.hidden = false
    results.hidden = true
  }
  function updateMagnet() {
    const hash = fXt.value.trim()
    magnetOut.value = hash
      ? buildMagnet(
          hash,
          fDn.value.trim(),
          parseInt(fXl.value, 10) || 0,
          fTr.value.split("\n"),
          fWs.value.split("\n"),
          fXs.value.split("\n"),
          fAs.value.split("\n"),
          fKt.value
        )
      : ""
  }
  async function handleFile(file) {
    errorEl.hidden = true
    if (!file || !file.name.endsWith(".torrent")) {
      showError("Please drop a .torrent file.")
      return
    }
    try {
      const buffer = await file.arrayBuffer()
      const { torrent, infoBytes } = PhoenixTorrent.parseTorrent(buffer)
      const hash = await PhoenixTorrent.sha1hex(infoBytes)
      const { name, size, trackers, webSeeds, exactSources } = extractInfo(torrent)
      fXt.value = hash
      fDn.value = name
      fXl.value = size || ""
      fTr.value = trackers.join("\n")
      fWs.value = webSeeds.join("\n")
      fXs.value = exactSources.join("\n")
      updateMagnet()
      results.hidden = false
    } catch (e) {
      showError(e.message)
    }
  }
  dropZone.addEventListener("dragover", (e) => {
    e.preventDefault()
    dropZone.classList.add("is-over")
  })
  dropZone.addEventListener("dragleave", () => dropZone.classList.remove("is-over"))
  dropZone.addEventListener("drop", (e) => {
    e.preventDefault()
    dropZone.classList.remove("is-over")
    handleFile(e.dataTransfer.files[0])
  })
  fileInput.addEventListener("change", () => handleFile(fileInput.files[0]))
  ;[fDn, fXl, fTr, fWs, fXs, fAs, fKt].forEach((el) => el.addEventListener("input", updateMagnet))
  copyBtn.addEventListener("click", () => {
    navigator.clipboard.writeText(magnetOut.value).then(() => {
      copyBtn.innerHTML = '<span class="ph-ico" data-lucide="check"></span>Copied'
      phInitIcons()
      setTimeout(() => {
        copyBtn.innerHTML = '<span class="ph-ico" data-lucide="copy"></span>Copy'
        phInitIcons()
      }, 1500)
    })
  })
})()
