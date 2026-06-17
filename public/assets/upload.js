/* Phoenix — Bulk Upload (assets/upload.js).
 * Picks/drops many .torrent files (or a folder) and POSTs each straight to the
 * authenticated add API. The CSRF token rides on #bulk[data-csrf]. */

;(function () {
  var root = document.getElementById("bulk")
  if (!root) return
  var csrf = root.getAttribute("data-csrf") || ""
  var drop = document.getElementById("bulk-drop")
  var fileInput = document.getElementById("bulk-file-input")
  var folderInput = document.getElementById("bulk-folder-input")
  var listed = document.getElementById("bulk-listed")
  var summary = document.querySelector("#bulk-summary div")
  var summaryBox = document.getElementById("bulk-summary")
  var resultsWrap = document.getElementById("bulk-results")
  var tbody = document.querySelector("#bulk-results tbody")
  var counts = { added: 0, exists: 0, failed: 0, total: 0 }
  var queue = []
  var busy = false
  var stopped = false
  var netFails = 0

  document.getElementById("bulk-files").addEventListener("click", function () {
    fileInput.click()
  })
  document.getElementById("bulk-folder").addEventListener("click", function () {
    folderInput.click()
  })
  fileInput.addEventListener("change", function () {
    addFiles(toArray(fileInput.files))
    fileInput.value = ""
  })
  folderInput.addEventListener("change", function () {
    addFiles(toArray(folderInput.files))
    folderInput.value = ""
  })
  ;["dragenter", "dragover"].forEach(function (ev) {
    drop.addEventListener(ev, function (e) {
      e.preventDefault()
      drop.classList.add("is-over")
    })
  })
  drop.addEventListener("dragleave", function (e) {
    e.preventDefault()
    drop.classList.remove("is-over")
  })
  drop.addEventListener("drop", function (e) {
    e.preventDefault()
    drop.classList.remove("is-over")
    var items = e.dataTransfer.items
    if (items && items.length && items[0].webkitGetAsEntry) {
      var entries = []
      for (var i = 0; i < items.length; i++) {
        var en = items[i].webkitGetAsEntry()
        if (en) entries.push(en)
      }
      Promise.all(entries.map(readEntry)).then(function (lists) {
        addFiles([].concat.apply([], lists))
      })
    } else {
      addFiles(toArray(e.dataTransfer.files))
    }
  })

  function toArray(list) {
    return Array.prototype.slice.call(list || [])
  }

  // Recursively collect File objects from a dropped directory entry.
  function readEntry(entry) {
    return new Promise(function (resolve) {
      if (!entry) {
        resolve([])
        return
      }
      if (entry.isFile) {
        entry.file(
          function (f) {
            resolve([f])
          },
          function () {
            resolve([])
          }
        )
        return
      }
      if (entry.isDirectory) {
        var reader = entry.createReader()
        var acc = []
        var readBatch = function () {
          reader.readEntries(
            function (ents) {
              if (!ents.length) {
                Promise.all(acc.map(readEntry)).then(function (lists) {
                  resolve([].concat.apply([], lists))
                })
                return
              }
              acc = acc.concat(toArray(ents))
              readBatch()
            },
            function () {
              resolve([])
            }
          )
        }
        readBatch()
        return
      }
      resolve([])
    })
  }

  function addFiles(files) {
    var torrents = files.filter(function (f) {
      return f && f.name && f.name.toLowerCase().slice(-8) === ".torrent"
    })
    if (!torrents.length) return
    resultsWrap.hidden = false
    summaryBox.hidden = false
    counts.total += torrents.length
    torrents.forEach(function (f) {
      var tr = document.createElement("tr")
      var name = document.createElement("td")
      name.className = "mono text-xs"
      name.textContent = f.name
      var status = document.createElement("td")
      status.className = "tar"
      status.innerHTML = '<span class="dim">Queued</span>'
      tr.appendChild(name)
      tr.appendChild(status)
      tbody.appendChild(tr)
      queue.push({ file: f, status: status })
    })
    updateSummary()
    if (!busy) pump()
  }

  async function pump() {
    busy = true
    while (queue.length && !stopped) {
      var job = queue.shift()
      await upload(job.file, job.status)
    }
    // Server gave up (repeated connection failures): don't keep firing
    // at a dead server — mark the rest and tell the operator.
    if (stopped) {
      queue.forEach(function (job) {
        job.status.innerHTML = '<span class="dim">Not attempted</span>'
      })
      queue.length = 0
      summaryBox.className = "alert alert-warning alert-center"
      summary.textContent =
        "Stopped: the server stopped responding after " +
        (counts.added + counts.exists + counts.failed) +
        " of " +
        counts.total +
        ". Reload and try a smaller batch."
    }
    busy = false
  }

  async function upload(file, status) {
    status.innerHTML = '<span class="dim">Uploading&hellip;</span>'
    var fd = new FormData()
    fd.append("torrent", file)
    fd.append("csrf", csrf)
    fd.append("listed", listed && listed.checked ? "1" : "0")
    try {
      var res = await fetch("/api/torrent/add.php", { method: "POST", body: fd, credentials: "same-origin" })
      var data = await res.json()
      netFails = 0 // reached the server and got a JSON reply
      if (data && data.torrent) {
        counts.added++
        status.innerHTML = '<span class="badge badge-green">Added</span>'
      } else if (data && data.error === "Torrent already exists.") {
        counts.exists++
        status.innerHTML = '<span class="badge">Already present</span>'
      } else {
        counts.failed++
        fail(status, (data && data.error) || "Failed")
      }
    } catch {
      // Network reset or non-JSON reply — the server is in trouble.
      counts.failed++
      netFails++
      fail(status, "Server error")
      if (netFails >= 3) stopped = true
    }
    updateSummary()
  }

  function updateSummary() {
    if (stopped) return // pump() owns the final message once stopped
    var done = counts.added + counts.exists + counts.failed
    summary.textContent =
      counts.added + " added · " + counts.exists + " already present · " + counts.failed + " failed — " + done + "/" + counts.total
  }

  // Set a danger badge with an untrusted message via textContent.
  function fail(cell, msg) {
    cell.innerHTML = ""
    var b = document.createElement("span")
    b.className = "badge text-danger"
    b.textContent = msg
    cell.appendChild(b)
  }
})()
