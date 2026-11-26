export function referencePhotos(device) {
  return Array.isArray(device?.reference_photos) ? device.reference_photos : []
}

export function primaryPhotoUrl(device) {
  if (device?.photo_path && device?.photo_url) return device.photo_url

  const reference = referencePhotos(device)
  if (reference.length) return reference[0].url || null

  return device?.photo_url || null
}

export function photoCount(device) {
  return (device?.photo_path ? 1 : 0) + referencePhotos(device).length
}

export function photoTypeLabel(photo) {
  const labels = {
    top: 'Top view',
    underside: 'Underside',
    bottom: 'Underside',
    receiver: 'Receiver',
    dongle: 'Receiver',
    marking: 'Markings / label',
    label: 'Markings / label',
    side: 'Side view',
    detail: 'Detail',
  }
  const type = String(photo?.photo_type || '').toLowerCase()

  return labels[type] || (type ? type.charAt(0).toUpperCase() + type.slice(1) : 'Reference photo')
}
