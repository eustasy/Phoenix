/* Phoenix — client-side .torrent parser (no dependencies).
 *
 * Parses a .torrent's bytes in the browser and returns the same normalised
 * shape the server's torrent_parse() produces, so a page can pre-fill a form
 * for the operator to amend before submitting (no upload needed). The info-hash
 * is the SHA-1 of the exact raw bytes of the info dict (never a re-encode).
 * Exposes window.PhoenixTorrent.torrentInfo(arrayBuffer) -> Promise<info>.
 */
(function (global) {
  function decode(bytes) {
    try { return new TextDecoder('utf-8').decode(bytes); } catch { return ''; }
  }

  // Returns { torrent, infoBytes } where infoBytes is the raw slice of the
  // info value, needed for the info-hash.
  function parseTorrent(arrayBuffer) {
    const bytes = new Uint8Array(arrayBuffer);
    let pos = 0;
    function readDigits(stopCode) { let s = ''; while (bytes[pos] !== stopCode) s += String.fromCharCode(bytes[pos++]); pos++; return s; }
    function parseString() { const len = parseInt(readDigits(58), 10); const slice = bytes.slice(pos, pos + len); pos += len; return slice; }
    function parseValue() {
      const b = bytes[pos];
      if (b === 105) { pos++; return parseInt(readDigits(101), 10); }
      if (b === 108) { pos++; const arr = []; while (bytes[pos] !== 101) arr.push(parseValue()); pos++; return arr; }
      if (b === 100) { pos++; const obj = {}; while (bytes[pos] !== 101) { const key = decode(parseString()); obj[key] = parseValue(); } pos++; return obj; }
      if (b >= 48 && b <= 57) return parseString();
      throw new Error('Unexpected byte ' + b + ' at position ' + pos);
    }
    if (bytes[pos++] !== 100) throw new Error('Torrent file is not a bencode dict.');
    const torrent = {}; let infoStart = -1, infoEnd = -1;
    while (bytes[pos] !== 101) {
      const key = decode(parseString());
      if (key === 'info') infoStart = pos;
      torrent[key] = parseValue();
      if (key === 'info' && infoStart >= 0) infoEnd = pos;
    }
    if (infoStart < 0) throw new Error('No info dict found in torrent file.');
    return { torrent, infoBytes: arrayBuffer.slice(infoStart, infoEnd) };
  }

  async function sha1hex(buffer) {
    if (!crypto || !crypto.subtle) throw new Error('SHA-1 requires a secure context (HTTPS or localhost).');
    const hash = await crypto.subtle.digest('SHA-1', buffer);
    return Array.from(new Uint8Array(hash)).map(b => b.toString(16).padStart(2, '0')).join('');
  }

  // Normalised info matching the server: info_hash, name, size, filename
  // (= name), files [{path,length}], trackers, webseeds. Unlike the magnet
  // generator, the tracker's own announce URL is NOT prepended — these are the
  // torrent's own fields, for the operator to keep or edit.
  async function torrentInfo(arrayBuffer) {
    const { torrent, infoBytes } = parseTorrent(arrayBuffer);
    const infoHash = await sha1hex(infoBytes);
    const info = torrent.info || {};

    const nameBytes = info['name.utf-8'] || info['name'];
    const name = nameBytes instanceof Uint8Array ? decode(nameBytes) : '';

    let size = 0;
    const files = [];
    if (Array.isArray(info.files)) {
      for (const f of info.files) {
        if (!f || typeof f.length !== 'number' || f.length < 0) continue;
        const partsRaw = f['path.utf-8'] || f['path'];
        if (!Array.isArray(partsRaw) || !partsRaw.length) continue;
        files.push({ path: partsRaw.map(p => decode(p)).join('/'), length: f.length });
        size += f.length;
      }
    } else if (typeof info.length === 'number' && info.length >= 0) {
      size = info.length;
      files.push({ path: name, length: size });
    }

    const trackers = [];
    const addTracker = (u) => { const s = u instanceof Uint8Array ? decode(u) : ''; if (s && !trackers.includes(s)) trackers.push(s); };
    if (torrent.announce) addTracker(torrent.announce);
    if (Array.isArray(torrent['announce-list'])) for (const tier of torrent['announce-list']) if (Array.isArray(tier)) for (const u of tier) addTracker(u);

    const webseeds = [];
    const addSeed = (u) => { const s = u instanceof Uint8Array ? decode(u) : ''; if (s && !webseeds.includes(s)) webseeds.push(s); };
    const ul = torrent['url-list'];
    if (ul instanceof Uint8Array) addSeed(ul); else if (Array.isArray(ul)) for (const u of ul) addSeed(u);

    return { infoHash, name, size, filename: name, files, trackers, webseeds };
  }

  global.PhoenixTorrent = { parseTorrent, decode, sha1hex, torrentInfo };
})(window);
