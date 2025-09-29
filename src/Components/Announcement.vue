<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="announcement">
		<div class="announcement__header">
			<h2 class="announcement__header__subject"
				:title="subject">
				{{ subject }}
			</h2>

			<div class="announcement__header__details">
				<div class="announcement__header__details__info">
					<NcAvatar :user="authorId"
						:display-name="author"
						:size="16"
						:show-user-status="false" />
					{{ author }}
					·
					<span v-if="isScheduled" :title="scheduledLabel">{{ scheduledLabel }}</span>
					<NcDateTime v-else
						ignore-seconds
						:format="{ timeStyle: 'short', dateStyle: 'long' }"
						:timestamp="time * 1000" />

					<template v-if="isAdmin">
						·
						<template v-if="isVisibleToEveryone">
							{{ visibilityLabel }}
						</template>
						<span v-else
							:title="visibilityTitle">
							{{ visibilityLabel }}
						</span>
					</template>
				</div>

				<NcActions v-if="isAdmin"
					:force-menu="true"
					:boundaries-element="boundariesElement">
					<NcActionButton v-if="notifications"
						:close-after-click="true"
						:name="t('announcementcenter', 'Clear notifications')"
						@click="onRemoveNotifications">
						<template #icon>
							<IconBellOffOutline size="20" />
						</template>
					</NcActionButton>
					<NcActionButton :name="t('announcementcenter', 'Delete announcement')"
						class="critical"
						@click="onDeleteAnnouncement">
						<template #icon>
							<IconTrashCanOutline size="20" />
						</template>
					</NcActionButton>
				</NcActions>
			</div>
		</div>

		<div v-if="coverPath" 
			class="announcement__cover"
			:style="{ backgroundImage: `url(${coverPath})` }"
			:title="t('announcementcenter', 'Click to view full size')"
			@click="onClickCover">
		</div>

		<!-- Pop-up для просмотра изображения -->
		<div v-if="showImageModal" class="image-modal" @click="closeImageModal">
			<div class="image-modal__content" @click.stop>
				<button class="image-modal__close" @click="closeImageModal" :title="t('announcementcenter', 'Close')">
					<IconClose :size="24" />
				</button>
				<img :src="coverPath" :alt="t('announcementcenter', 'Cover image')" class="image-modal__image" />
			</div>
		</div>

		<div v-if="message"
			class="announcement__message"
			@click="onClickFoldedMessage">
			<NcRichText :text="message"
				:arguments="{}"
				:autolink="true"
				:use-markdown="true"
				:class="{'announcement__message--folded': isMessageFolded}" />

			<div v-if="isMessageFolded"
				class="announcement__message__overlay" />
		</div>

		<NcButton v-if="comments !== false"
			type="tertiary"
			class="announcement__comments"
			@click="onClickCommentCount">
			{{ commentsCount }}
		</NcButton>
	</div>
</template>

<script>
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import { getLanguage } from '@nextcloud/l10n'
import {
	showError,
} from '@nextcloud/dialogs'
import {
	deleteAnnouncement,
	removeNotifications,
} from '../services/announcementsService.js'
import IconBellOffOutline from 'vue-material-design-icons/BellOffOutline.vue'
import IconTrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import IconClose from 'vue-material-design-icons/Close.vue'

export default {
	name: 'Announcement',
	components: {
		IconBellOffOutline,
		IconTrashCanOutline,
		IconClose,
		NcActions,
		NcActionButton,
		NcAvatar,
		NcButton,
		NcDateTime,
		NcRichText,
	},
	props: {
		isAdmin: {
			type: Boolean,
			required: true,
		},
		id: {
			type: Number,
			required: true,
		},
		authorId: {
			type: String,
			required: true,
		},
		author: {
			type: String,
			required: true,
		},
		time: {
			type: Number,
			required: true,
		},
		subject: {
			type: String,
			required: true,
		},
		message: {
			type: String,
			required: true,
		},
		groups: {
			type: Array,
			required: true,
		},
		comments: {
			type: [Boolean, Number],
			required: true,
		},
		notifications: {
			type: Boolean,
			required: true,
		},
		scheduleTime: {
			type: Number,
			required: false,
			default: null,
		},
		coverPath: {
			type: String,
			required: false,
			default: null,
		},
	},

	data() {
		return {
			isMessageFolded: true,
			showImageModal: false,
		}
	},

	computed: {
		boundariesElement() {
			return document.querySelector(this.$el)
		},

		scheduleDateFormat() {
			return (new Date(this.scheduleTime * 1000)).toLocaleString(getLanguage(), { dateStyle: 'long', timeStyle: 'short' })
		},

		isVisibleToEveryone() {
			return this.groups.length === 0
				|| this.groups.filter(({ id }) => {
					return id === 'everyone'
				}).length === 1
		},

		visibilityLabel() {
			if (this.isVisibleToEveryone) {
				return t('announcementcenter', 'visible to everyone')
			}

			if (this.groups.length === 1) {
				return t('announcementcenter', 'visible to group {name}', this.groups[0])
			}
			if (this.groups.length === 2) {
				return t('announcementcenter', 'visible to groups {name1} and {name2}', {
					name1: this.groups[0].name,
					name2: this.groups[1].name,
				})
			}
			return n(
				'announcementcenter',
				'visible to group {name} and %n more',
				'visible to group {name} and %n more',
				this.groups.length - 1,
				this.groups[0],
			)
		},

		isScheduled() {
			return this.scheduleTime && this.scheduleTime !== null
		},

		scheduledLabel() {
			return t('announcementcenter', 'scheduled at {time}', { time: this.scheduleDateFormat })
		},

		visibilityTitle() {
			if (this.isVisibleToEveryone) {
				return ''
			}

			return this.groups.map(({ name }) => {
				return name
			}).join(t('announcementcenter', ', '))
		},

		commentsCount() {
			return n('announcementcenter', '%n comment', '%n comments', this.comments)
		},
	},

	mounted() {
		if (this.message.length <= 200) {
			this.isMessageFolded = false
		}
		
		// Добавляем обработчик клавиши Escape
		document.addEventListener('keydown', this.handleKeydown)
	},

	beforeDestroy() {
		// Удаляем обработчик клавиши Escape
		document.removeEventListener('keydown', this.handleKeydown)
	},

	methods: {
		onClickCover() {
			this.showImageModal = true
		},

		closeImageModal() {
			this.showImageModal = false
		},

		handleKeydown(event) {
			if (event.key === 'Escape' && this.showImageModal) {
				this.closeImageModal()
			}
		},
		onClickCommentCount() {
			this.$emit('click', this.id)
		},
		onClickFoldedMessage() {
			this.isMessageFolded = false
			if (this.comments !== false) {
				this.$emit('click', this.id)
			}
		},
		async onRemoveNotifications() {
			try {
				await removeNotifications(this.id)
				this.$store.dispatch('removeNotifications', this.id)
			} catch (e) {
				console.error(e)
				showError(t('announcementcenter', 'An error occurred while removing the notifications of the announcement'))
			}
		},
		async onDeleteAnnouncement() {
			try {
				await deleteAnnouncement(this.id)
				this.$store.dispatch('deleteAnnouncement', this.id)
			} catch (e) {
				console.error(e)
				showError(t('announcementcenter', 'An error occurred while deleting the announcement'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
	.announcement {
		max-width: 690px;
		padding: 0 10px;
		margin: 0 auto 3em;
		font-size: 15px;

		&:nth-child(1) {
			margin-top: 70px;
		}

		&__header {
			&__details {
				display: flex;

				&__info {
					color: var(--color-text-maxcontrast);
					flex: 1 1 auto;
					display: flex;
					align-items: center;

					:deep(.avatardiv) {
						margin-right: 4px;
					}

					span {
						margin-left: 4px;
						margin-right: 4px;
					}
				}

				.action-item {
					display: flex;
					flex: 0 0 44px;
					position: relative;
				}
			}
		}

     &__cover {
       margin-top: 16px;
       border-radius: var(--border-radius-large);
       overflow: hidden;
       cursor: pointer;
       transition: transform 0.2s ease;
       width: 100%;
       height: 250px;
       background-size: cover;
       background-position: center;
       background-repeat: no-repeat;
       display: block;

       &:hover {
         transform: scale(1.02);
       }
     }

		&__message {
			position: relative;
			margin-top: 20px;

			&--folded {
				overflow: hidden;
				text-overflow: ellipsis;
				display: -webkit-box;
				-webkit-line-clamp: 7;
				-webkit-box-orient: vertical;
				cursor: pointer;
			}

			&__overlay {
				position: absolute;
				bottom: 0;
				height: 3.2em;
				width: 100%;
				cursor: pointer;
				background: linear-gradient(
					rgba(255, 255, 255, 0),
					var(--color-main-background)
				);
			}
		}

		&__comments {
			margin-left: -16px;
		}
	}

	.critical > :deep(.action-button) {
		color: var(--color-text-error);
	}

	// Стили для модального окна изображения
	.image-modal {
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background-color: rgba(0, 0, 0, 0.8);
		display: flex;
		justify-content: center;
		align-items: center;
		z-index: 10000;
		cursor: pointer;

		&__content {
			position: relative;
			max-width: 95%;
			max-height: 95%;
			background: var(--color-main-background);
			border-radius: var(--border-radius-large);
			padding: 10px;
			cursor: default;
		}

		&__close {
			position: absolute;
			top: 10px;
			right: 10px;
			background: var(--color-background-hover);
			border: none;
			border-radius: 50%;
			width: 40px;
			height: 40px;
			display: flex;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			z-index: 10001;
			transition: background-color 0.2s ease;

			&:hover {
				background: var(--color-background-dark);
			}
		}

		&__image {
			max-width: 100%;
			max-height: 90vh;
			object-fit: contain;
			display: block;
		}
	}
</style>
