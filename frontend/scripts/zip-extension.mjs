/**
 * Packs dist/ into gastroflowx-extension.zip for the Chrome Web Store.
 * Plain Node (no `zip` binary needed, works on Windows). The manifest "key"
 * field is dropped: the Web Store rejects packages that contain it and signs
 * the extension with its own key (VITE_EXTENSION_KEY is for local builds).
 */
import { readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs'
import { join, relative, resolve, sep } from 'node:path'
import { fileURLToPath } from 'node:url'
import { deflateRawSync } from 'node:zlib'

const root = resolve(fileURLToPath(import.meta.url), '../..')
const dist = join(root, 'dist')
const out = join(root, 'gastroflowx-extension.zip')

const CRC_TABLE = Array.from({ length: 256 }, (_, n) => {
  let c = n
  for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1
  return c >>> 0
})
const crc32 = (buf) => {
  let c = 0xffffffff
  for (const byte of buf) c = CRC_TABLE[(c ^ byte) & 0xff] ^ (c >>> 8)
  return (c ^ 0xffffffff) >>> 0
}

const files = (dir) =>
  readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)
    return statSync(path).isDirectory() ? files(path) : [path]
  })

const locals = []
const centrals = []
let offset = 0

for (const path of files(dist).sort()) {
  const name = relative(dist, path).split(sep).join('/')
  let data = readFileSync(path)
  if (name === 'manifest.json') {
    const manifest = JSON.parse(data)
    delete manifest.key
    data = Buffer.from(JSON.stringify(manifest, null, 2))
  }
  const deflated = deflateRawSync(data, { level: 9 })
  const stored = deflated.length >= data.length
  const body = stored ? data : deflated
  const nameBuf = Buffer.from(name, 'utf8')
  const crc = crc32(data)

  const local = Buffer.alloc(30)
  local.writeUInt32LE(0x04034b50, 0)
  local.writeUInt16LE(20, 4)
  local.writeUInt16LE(0x0800, 6) // UTF-8 names
  local.writeUInt16LE(stored ? 0 : 8, 8)
  local.writeUInt32LE(0x00210000, 10) // 1980-01-01 00:00
  local.writeUInt32LE(crc, 14)
  local.writeUInt32LE(body.length, 18)
  local.writeUInt32LE(data.length, 22)
  local.writeUInt16LE(nameBuf.length, 26)

  const central = Buffer.alloc(46)
  central.writeUInt32LE(0x02014b50, 0)
  central.writeUInt16LE(20, 4)
  central.writeUInt16LE(20, 6)
  central.writeUInt16LE(0x0800, 8)
  central.writeUInt16LE(stored ? 0 : 8, 10)
  central.writeUInt32LE(0x00210000, 12)
  central.writeUInt32LE(crc, 16)
  central.writeUInt32LE(body.length, 20)
  central.writeUInt32LE(data.length, 24)
  central.writeUInt16LE(nameBuf.length, 28)
  central.writeUInt32LE(offset, 42)

  locals.push(local, nameBuf, body)
  centrals.push(central, nameBuf)
  offset += local.length + nameBuf.length + body.length
}

const centralSize = centrals.reduce((n, b) => n + b.length, 0)
const end = Buffer.alloc(22)
end.writeUInt32LE(0x06054b50, 0)
end.writeUInt16LE(centrals.length / 2, 8)
end.writeUInt16LE(centrals.length / 2, 10)
end.writeUInt32LE(centralSize, 12)
end.writeUInt32LE(offset, 16)

writeFileSync(out, Buffer.concat([...locals, ...centrals, end]))
console.log(`✓ ${relative(root, out)} (${centrals.length / 2} files)`)
